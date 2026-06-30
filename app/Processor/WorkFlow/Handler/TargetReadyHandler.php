<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Transfer\TransferService;
use App\Processor\WorkFlow\WorkFlow;
use WecarSwoole\Container;
use Psr\Log\LoggerInterface;

/**
 * Target file ready handler
 */
class TargetReadyHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_OBJECT_READY;
    }

    /**
     * Upload to the storage server
     */
    protected function exec()
    {
        try {
            Container::get(TransferService::class)->upload($this->task());
            $this->notify(WorkFlow::WF_UPLOAD_SUC);
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error($e->getMessage() . "taskid:" . $this->workFlow->task()->id(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            $this->notify(WorkFlow::WF_UPLOAD_FAILED, $e->getMessage());
        }
    }
}
