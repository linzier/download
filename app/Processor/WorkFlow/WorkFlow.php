<?php

namespace App\Processor\WorkFlow;

use App\Domain\Task\Task;
use App\Domain\Task\TaskService;
use App\Processor\WorkFlow\Handler\TargetReadyHandler;
use App\Processor\WorkFlow\Handler\SourceReadyHandler;
use App\Processor\WorkFlow\Handler\ToDoHandler;
use App\Processor\WorkFlow\Handler\UploadSuccessHandler;
use App\Processor\WorkFlow\Handler\WorkHandler;
use EasySwoole\Component\Singleton;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;

/**
 * Workflow
 * Implements a handler chain using a linked list
 */
class WorkFlow
{
    use Singleton;

    /**
     * Workflow status definitions
     * Every status except the initial and terminal states must have a corresponding handler.
     */
    // Workflow initialized (no handler; marks the initial state only)
    public const WF_INIT = 1;
    // Pending
    public const WF_TODO = 2;
    // Source data ready (all source data has been pulled to local temp files; note: an empty response from the remote API also counts as ready)
    public const WF_SOURCE_READY = 3;
    // Source data fetch failed (not all source data was retrieved; the remote API may be unavailable)
    public const WF_SOURCE_FAILED = 4;
    // Target file ready (target file has been generated locally)
    public const WF_OBJECT_READY = 5;
    // Target file generation failed
    public const WF_OBJECT_FAILED = 6;
    // Upload succeeded
    public const WF_UPLOAD_SUC = 7;
    // Upload failed
    public const WF_UPLOAD_FAILED = 8;
    // Client notification succeeded
    public const WF_NOTIFY_DONE = 9;
    // Client notification failed
    public const WF_NOTIFY_FAIL = 10;

    // First handler node in the workflow
    private const FIRST_STATUS = self::WF_TODO;

    // Statuses that indicate workflow failure
    private const FAILED_ENDS = [
        self::WF_SOURCE_FAILED,
        self::WF_OBJECT_FAILED,
        self::WF_UPLOAD_FAILED,
        self::WF_NOTIFY_FAIL,
    ];

    /**
     * @var WorkHandler Head handler node, used to initiate the handler chain
     */
    private $head;
    /**
     * @var WorkHandler Tail handler node, used to append new handlers
     */
    private $tail;
    /**
     * @var Task The task associated with this workflow
     */
    private $task;
    /**
     * @var int Current workflow execution status
     */
    private $currentStatus;
    // List of statuses (handler nodes) this workflow can handle
    private $handleStatus = [];

    private function __construct(Task $task)
    {
        $this->task = $task;
        $this->currentStatus = self::WF_INIT;
    }

    /**
     * Get the task associated with this workflow
     */
    public function task(): Task
    {
        return $this->task;
    }

    /**
     * Get the current workflow status
     */
    public function status(): int
    {
        return $this->currentStatus;
    }

    /**
     * Start the workflow
     */
    public function start()
    {
        if ($this->currentStatus != self::WF_INIT) {
            return;
        }
        
        $this->notify(self::FIRST_STATUS);
    }

    /**
     * Notify the workflow to advance to the given status.
     * Some steps may run in separate coroutines or task worker processes,
     * so this method is used to asynchronously notify the workflow engine of the current progress.
     */
    public function notify(int $workStatus, string $msg = '')
    {
        $this->currentStatus = $workStatus;
        if (!in_array($workStatus, $this->handleStatus)) {
            // No handler for this status; finalize the workflow
            return $this->finishWorkFlow($workStatus, $msg);
        }

        $this->handle($workStatus);
    }

    /**
     * Manually destroy the workflow to break circular references between WorkFlow and WorkHandler.
     */
    public function destroy()
    {
        $this->head = $this->tail = $this->task = null;
    }

    /**
     * Create a new workflow
     */
    public static function newWorkFlow(Task $task): WorkFlow
    {
        $workFlow = new self($task);

        // Register handler nodes
        $workFlow->addHandler(new ToDoHandler($workFlow))
             ->addHandler(new SourceReadyHandler($workFlow))
             ->addHandler(new TargetReadyHandler($workFlow))
             ->addHandler(new UploadSuccessHandler($workFlow));

        return $workFlow;
    }

    protected function finishWorkFlow(int $status, string $msg)
    {
        $task = $this->task;
        try {
            // Update task status
            Container::get(TaskService::class)->switchStatus($task, !in_array($status, self::FAILED_ENDS) ? Task::STATUS_SUC : Task::STATUS_FAILED, $msg);
            Container::get(LoggerInterface::class)->info("Task processing finished: {$task->id()}, task status: {$task->status()}, msg: {$msg}");
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error("Task processing finished: {$task->id()}, task status: {$task->status()}, msg: {$msg}. Status transition failed, exception: " . $e->getMessage() . ". trace: " . $e->getTraceAsString());
        }
    }

    /**
     * Execute a workflow handler node
     */
    protected function handle(int $workStatus)
    {
        $this->head->handle($workStatus);
    }

    /**
     * Register a handler node
     */
    private function addHandler(WorkHandler $workHandler): WorkFlow
    {
        if (in_array($workHandler->handleStatus(), $this->handleStatus)) {
            return $this;
        }

        if (!$this->head) {
            $this->head = $workHandler;
        } else {
            $this->tail->setSuccessor($workHandler);
        }
        $this->tail = $workHandler;

        $this->handleStatus[] = $workHandler->handleStatus();

        return $this;
    }
}
