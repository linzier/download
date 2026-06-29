<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Target\TargetService;
use App\Processor\WorkFlow\WorkFlow;
use WecarSwoole\Container;
use Psr\Log\LoggerInterface;

/**
 * 源数据就绪处理程序
 */
class SourceReadyHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_SOURCE_READY;
    }

    /**
     * 生成目标数据
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
