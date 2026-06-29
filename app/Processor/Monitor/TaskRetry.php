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
 * 任务失败重试
 * 只处理 24 小时内创建的
 * 以下状态需要重试：
 *  1. 待处理（状态码：1，未入列）：
 *      a. 入列失败；
 *      b. 入列成功但改状态失败；
 *      c. 从其它异常状态转成待处理状态的；
 *    重试方案：15 分钟重试，直接入列
 *  2. 已入列（状态码：2）：
 *      a. 队列崩溃；
 *      b. 队列堵塞；
 *      c. 取出后改状态前程序崩溃；
 *    重试方案：入列 10 分钟内的不处理；超过 10 分钟的，先判断队列是否为空，如果队列不为空，则超过 30 分钟的处理，30 分钟内的不处理；队列为空，则立即处理。
 *            处理方式：将状态改成“待处理”
 *  3. 处理中（状态码：3）：
 *      a. 程序还在处理；
 *      b. 程序挂了；
 *    重试方案：超过 1 小时的重新处理（时间可配置，可在投递任务接口传参设置）
 *  4. 可重试失败（状态码：5）：
 *    重试方案：立即将状态改成待处理；
 * 注意：以上都是先将状态改成“待处理”，然后再入列，因为在状态机设置上，只有待处理状态的才能入列
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
                
                // 需要重新处理
                $task = TaskFactory::create($taskDTO);
                // 先将任务状态改成“待处理”，否则后面状态切换会失败
                if ($taskDTO->status != Task::STATUS_TODO) {
                    $this->taskSvr->switchStatus($task, Task::STATUS_TODO);
                }

                // 投递任务
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
                // 待处理，用任务创建时间比较
                return $taskDTO->ctime <= $now - 60 * 15;
            case Task::STATUS_ENQUEUED:
                // 已入列，30s 内的不处理
                if ($taskDTO->qtime > $now - 30) {
                    return false;
                }

                // 看看队列情况，队列空的话需要立即处理（队列里面没有该任务，说明很可能处理异常）
                if (!Queue::instance(Config::getInstance()->getConf('task_queue'))->size()) {
                    return true;
                }

                // 30 分钟后的处理
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
