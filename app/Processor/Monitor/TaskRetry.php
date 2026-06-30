<?php

namespace App\Processor\Monitor;

use App\Domain\Task\ITaskRepository;
use App\Domain\Task\Task;
use App\Domain\Task\TaskFactory;
use App\Domain\Task\TaskService;
use App\Foundation\DTO\DBTaskDTO;
use App\Foundation\Queue\Queue;
use App\Processor\TaskManager;
use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;

/**
 * Failed task retry mechanism.
 * Only processes tasks created within the last 24 hours.
 * The following statuses require retry:
 *  1. Pending (status code: 1, not enqueued):
 *      a. Enqueue failed;
 *      b. Enqueue succeeded but status update failed;
 *      c. Transitioned to pending from another abnormal status;
 *    Retry strategy: retry after 15 minutes by enqueuing directly.
 *  2. Enqueued (status code: 2):
 *      a. Queue crashed;
 *      b. Queue is blocked;
 *      c. Process crashed after dequeue but before status update;
 *    Retry strategy: skip if enqueued within 10 minutes; after 10 minutes, check if the queue is empty --
 *            if not empty, retry tasks enqueued over 30 minutes ago; if empty, retry immediately.
 *            Retry method: reset status to "pending".
 *  3. In progress (status code: 3):
 *      a. Task is still being processed;
 *      b. Process has crashed;
 *    Retry strategy: retry tasks running over 1 hour (configurable; can be set per task via the submission API).
 *  4. Retryable failure (status code: 5):
 *    Retry strategy: immediately reset status to pending.
 * Note: In all cases above, the status is first changed to "pending" before enqueuing,
 * because the state machine only allows enqueuing from the pending status.
 */
class TaskRetry
{
    use Singleton;

    /**
     * @var TaskService
     */
    private $taskSvr;
    /**
     * @var ITaskRepository
     */
    private $taskRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct()
    {
        $this->taskSvr = Container::get(TaskService::class);
        $this->taskRepos = Container::get(ITaskRepository::class);
        $this->logger = Container::get(LoggerInterface::class);
    }

    public function watch()
    {
        $now = time();
        $taskDTOs = $this->taskRepos->getTaskDTOsToRetry(
            [Task::STATUS_TODO, Task::STATUS_ENQUEUED, Task::STATUS_DOING, Task::STATUS_FAILED],
            $now - 86400,
            $now - 60,
            Task::MAX_RETRY_NUM
        );

        if (!$taskDTOs) {
            return;
        }
        
        foreach ($taskDTOs as $taskDTO) {
            try {
                if ($taskDTO->isSync || !$this->willRetry($taskDTO)) {
                    continue;
                }
                
                // Needs reprocessing
                $task = TaskFactory::create($taskDTO);
                // Reset task status to "pending" first, otherwise subsequent status transition will fail
                if ($taskDTO->status != Task::STATUS_TODO) {
                    $this->taskSvr->switchStatus($task, Task::STATUS_TODO);
                }

                // Submit the task
                TaskManager::getInstance()->deliver($task);
            } catch (\Throwable $e) {
                $this->logger->error("retry task error.enqueue error:{$e->getMessage()}");
            }
        }
    }

    private function willRetry(DBTaskDTO $taskDTO): bool
    {
        $now = time();
        switch ($taskDTO->status) {
            case Task::STATUS_TODO:
                // Pending: compare by task creation time
                return $taskDTO->ctime <= $now - 60 * 15;
            case Task::STATUS_ENQUEUED:
                // Enqueued: skip if enqueued within 30 seconds
                if ($taskDTO->qtime > $now - 30) {
                    return false;
                }

                // Check queue status: if the queue is empty, retry immediately (task missing from queue likely indicates abnormal processing)
                if (!Queue::instance(Config::getInstance()->getConf('task_queue'))->size()) {
                    return true;
                }

                // Retry if enqueued over 30 minutes ago
                return $taskDTO->qtime <= $now - 60 * 30;
            case Task::STATUS_DOING:
                $expire = $taskDTO->maxExecTime ?: Config::getInstance()->getConf('max_exec_time') ?: 3600;
                return $taskDTO->etime <= $now - $expire;
            case Task::STATUS_FAILED:
                return true;
        }

        return false;
    }
}
