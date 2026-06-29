<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Transfer\TransferService;
use App\Processor\WorkFlow\WorkFlow;
use WecarSwoole\Container;
use Psr\Log\LoggerInterface;

/**
 * 目标文件就绪处理程序
 */
class TargetReadyHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_OBJECT_READY;
    }

    /**
     * 上传到存储服务器
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
