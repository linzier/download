<?php

namespace App\Processor\WorkFlow\Handler;

use App\Domain\Task\Task;
use App\Processor\WorkFlow\WorkFlow;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;

/**
 * 工作流节点处理器基类
 * 采用职责链模式实现
 * 每个处理程序检查是否需要处理当前的任务，如果不需要处理，则交给下一个处理程序，如果需要自己处理，则处理，处理后不再交给后面的处理程序
 * 注意：处理程序本身不要写具体的业务实现代码，业务实现由领域层类实现，处理程序根据领域层执行情况通知后续状态
 * 工作流节点本身可以采用子协程并发、异步 task 等方式执行任务
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
     * 设置下游处理程序
     */
    public function setSuccessor(WorkHandler $successor)
    {
        $this->successor = $successor;
    }

    /**
     * 处理逻辑
     * @param int $workStatus 工作流执行状态
     */
    public function handle(int $workStatus)
    {
        if ($this->handleStatus() !== $workStatus) {
            // 本处理程序不需要处理，交给下游
            $this->successor->handle($workStatus);
            return;
        }

        // 自己能处理，则处理掉，同时不再传递给下游
        Container::get(LoggerInterface::class)->info("处理任务{$this->workFlow->task()->id()}的状态{$workStatus}");
        $this->exec();
    }

    /**
     * 每个处理程序负责处理的工作流节点状态
     * 只有符合自己状态的处理请求到来时才会处理
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
     * 每个处理程序具体的处理逻辑
     */
    abstract protected function exec();
}
