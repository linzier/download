<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Task\Task;
use App\Processor\WorkFlow\WorkFlow;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;

/**
 * Base class for workflow handler nodes.
 * Implements the chain of responsibility pattern.
 * Each handler checks whether it should process the current status; if not, it delegates to the next handler.
 * If it handles the request, processing stops and the request is not passed further down the chain.
 * Note: Handlers should not contain business logic directly; business logic belongs in the domain layer.
 * Handlers notify the workflow of the next status based on domain layer execution results.
 * Handler nodes may use coroutine concurrency, async task workers, or other mechanisms to execute tasks.
 */
abstract class WorkHandler
{
    /**
     * @var WorkHandler
     */
    protected $successor;
    /**
     * @var WorkFlow
     */
    protected $workFlow;

    public function __construct(WorkFlow $workFlow)
    {
        $this->workFlow = $workFlow;
    }

    /**
     * Set the downstream handler
     */
    public function setSuccessor(WorkHandler $successor)
    {
        $this->successor = $successor;
    }

    /**
     * Handle the workflow status
     * @param int $workStatus Workflow execution status
     */
    public function handle(int $workStatus)
    {
        if ($this->handleStatus() !== $workStatus) {
            // This handler does not handle the current status; delegate to the next handler
            $this->successor->handle($workStatus);
            return;
        }

        // This handler can handle the status; process it without passing downstream
        Container::get(LoggerInterface::class)->info("Handling task {$this->workFlow->task()->id()} status {$workStatus}");
        $this->exec();
    }

    /**
     * The workflow status this handler is responsible for.
     * Only processes requests matching its own status.
     */
    abstract public function handleStatus(): int;

    protected function notify(int $status, string $msg = '')
    {
        $this->workFlow->notify($status, $msg);
    }

    protected function task(): Task
    {
        return $this->workFlow->task();
    }

    /**
     * Concrete processing logic for each handler
     */
    abstract protected function exec();
}
