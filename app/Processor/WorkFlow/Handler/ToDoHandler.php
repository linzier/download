<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Source\SourceService;
use App\Processor\WorkFlow\WorkFlow;
use WecarSwoole\Container;
use Psr\Log\LoggerInterface;

/**
 * 待处理处理程序
 */
class ToDoHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_TODO;
    }

    /**
     * 拉取源数据
     */
    protected function exec()
    {
        try {
            // 获取数据
            Container::get(SourceService::class)->fetch($this->task()->source(), $this->task()->target());
            $this->notify(WorkFlow::WF_SOURCE_READY);
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error($e->getMessage() . "taskid:" . $this->workFlow->task()->id(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            $this->notify(WorkFlow::WF_SOURCE_FAILED, $e->getMessage());
        }
    }
}
