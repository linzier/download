<?php

namespace App\Processor;

use App\Domain\Task\Task;
use App\Domain\Task\TaskService;
use App\Foundation\Queue\Queue;
use App\Processor\WorkFlow\WorkFlow;
use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\Queue\Job;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine;
use WecarSwoole\Container;

/**
 * Task Manager
 * The task manager is a singleton that maintains the lifecycle of multiple tasks within this process.
 * Each task corresponds to a workflow, so the task manager also manages the workflow lifecycle.
 * Note: The workflow concept is an internal detail; the task manager must not expose it externally.
 */
class TaskManager
{
    use Singleton;

    // Working
    private const STATUS_WORKING = 1;
    // Waiting for restart (waiting for other tasks to complete; no longer accepting new tasks)
    private const STATUS_STOP_WAITING = 2;
    // Restarting
    private const STATUS_STOPPING = 3;

    // List of workflows currently being processed
    private $workFlows = [];
    // Total number of tasks processed by this task manager
    private $procCount;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var TaskService
     */
    private $taskSvr;
    private $status;

    private function __construct()
    {
        $this->procCount = 0;
        $this->logger = Container::get(LoggerInterface::class);
        $this->taskSvr = Container::get(TaskService::class);
        $this->status = self::STATUS_WORKING;
    }

    /**
     * Enqueue a task
     */
    public function deliver(Task $task)
    {
        $job = new Job();
        $job->setJobData(['task_id' => $task->id(), 'enqueue_time' => time()]);
        if (Queue::instance(Config::getInstance()->getConf('task_queue'))->producer()->push($job)) {
            // Note: In earlier versions, the task status was updated to "enqueued" here. However, due to multi-process concurrency issues (another process may dequeue and process immediately after enqueueing), this status update has been removed to avoid race conditions.
            $this->logger->info("Task enqueued to message queue: {$task->id()}");
        } else {
            $this->logger->error("Failed to enqueue task to message queue: {$task->id()}");
        }
    }

    /**
     * Process a task
     */
    public function process(Task $task)
    {
        // Acquire process-level ticket
        Ticket::get("task_source");

        // Execute in a new coroutine
        go(function () use ($task) {
            try {
                // Switch task status to in-progress
                $this->taskSvr->switchStatus($task, Task::STATUS_DOING);
                $this->logger->info("Start processing task: {$task->id()}");
                $this->getWorkFlow($task)->start();
            } catch (\Throwable $e) {
                // Switch task status to failed
                try {
                    $this->taskSvr->switchStatus($task, Task::STATUS_FAILED, "Task {$task->id()} processing error: {$e->getMessage()}");
                } catch (\Throwable $e) {
                    // Ignore if re-throwing fails
                }
                $this->logger->error("Task {$task->id()} processing error: {$e->getMessage()}");
            } finally {
                // Cleanup
                $this->clear($task);
                // Return ticket
                Ticket::done("task_source");
                $this->procCount++;
                // Check if task manager needs to be restarted
                $this->tryToReboot();
            }
        });
    }

    private function tryToReboot()
    {
        if ($this->status != self::STATUS_WORKING || $this->procCount < intval(Config::getInstance()->getConf('task_max_process'))) {
            return;
        }

        $this->status = self::STATUS_STOP_WAITING;
        // Stop queue listener
        QueueListener::stop();

        // Poll the workflow list until it is empty or the timeout (15 minutes) is reached, then restart the process
        $this->logger->info("Process service term expired, initiating restart. pid:" . getmypid());
        $cnt = 0;
        while (!empty($this->workFlows) && $cnt++ < 900) {
            Coroutine::sleep(1);
        }

        $this->stop();
    }

    private function stop()
    {
        $this->status = self::STATUS_STOPPING;
        $this->logger->info("Process restarting. pid:" . getmypid());
        $server = ServerManager::getInstance()->getSwooleServer();
        $server->stop($server->worker_id, true);
    }

    /**
     * Get the workflow for the given task
     */
    private function getWorkFlow(Task $task): WorkFlow
    {
        if (!isset($this->workFlows[$task->id()])) {
            $this->initWorkFlow($task);
        }

        return $this->workFlows[$task->id()];
    }
    
    /**
     * Initialize workflow
     */
    private function initWorkFlow(Task $task)
    {
        if (isset($this->workFlows[$task->id()])) {
            return;
        }

        $this->workFlows[$task->id()] = WorkFlow::newWorkFlow($task);
    }

    /**
     * Cleanup after task processing is complete
     */
    private function clear(Task $task)
    {
        // Cleanup workflow
        if (isset($this->workFlows[$task->id()])) {
            $wStatus = $this->workFlows[$task->id()]->status();
            $this->workFlows[$task->id()]->destroy();
            unset($this->workFlows[$task->id()]);

            $this->logger->info("Workflow cleaned up, task {$task->id()}, workflow status: {$wStatus}");
        }
    }
}
