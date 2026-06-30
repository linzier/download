<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Target\TargetService;
use App\Processor\WorkFlow\WorkFlow;
use WecarSwoole\Container;
use Psr\Log\LoggerInterface;

/**
 * Source data ready handler
 */
class SourceReadyHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_SOURCE_READY;
    }

    /**
     * Generate target data
     */
    protected function exec()
    {
        try {
            Container::get(TargetService::class)->generate($this->task()->source(), $this->task()->target(), true);
            $this->notify(WorkFlow::WF_OBJECT_READY);
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error($e->getMessage() . "taskid:" . $this->workFlow->task()->id(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            $this->notify(WorkFlow::WF_OBJECT_FAILED, $e->getMessage());
        }
    }
}
