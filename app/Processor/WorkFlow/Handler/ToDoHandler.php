<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Source\SourceService;
use App\Processor\WorkFlow\WorkFlow;
use WecarSwoole\Container;
use Psr\Log\LoggerInterface;

/**
 * Pending (to-do) handler
 */
class ToDoHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_TODO;
    }

    /**
     * Fetch source data
     */
    protected function exec()
    {
        try {
            // Fetch data
            Container::get(SourceService::class)->fetch($this->task()->source(), $this->task()->target());
            $this->notify(WorkFlow::WF_SOURCE_READY);
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error($e->getMessage() . "taskid:" . $this->workFlow->task()->id(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            $this->notify(WorkFlow::WF_SOURCE_FAILED, $e->getMessage());
        }
    }
}
