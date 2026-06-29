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
 * 工作流
 * 使用链表实现调用链
 */
class WorkFlow
{
    use Singleton;

    /**
     * 工作流状态定义
     * 除了初始态和结束态，其他状态必须要有对应的节点处理程序
     */
    // 工作流初始化（该状态没有对应的节点处理程序，仅用来标志工作流初始态）
    public const WF_INIT = 1;
    // 待处理
    public const WF_TODO = 2;
    // 源数据就绪（源数据已经全部拉到本地形成临时文件。注意：对方接口返回空数据也认为是就绪）
    public const WF_SOURCE_READY = 3;
    // 源数据获取失败 （未成功获取全部源数据，可能对方接口不可用）
    public const WF_SOURCE_FAILED = 4;
    // 目标文件就绪（已生成目标文件到本地）
    public const WF_OBJECT_READY = 5;
    // 目标文件生成失败
    public const WF_OBJECT_FAILED = 6;
    // 上传完成
    public const WF_UPLOAD_SUC = 7;
    // 上传失败
    public const WF_UPLOAD_FAILED = 8;
    // 通知客户端完成
    public const WF_NOTIFY_DONE = 9;
    public const WF_NOTIFY_FAIL = 10;

    // 工作流第一个执行节点
    private const FIRST_STATUS = self::WF_TODO;

    // 哪些状态表示工作流执行失败
    private const FAILED_ENDS = [
        self::WF_SOURCE_FAILED,
        self::WF_OBJECT_FAILED,
        self::WF_UPLOAD_FAILED,
        self::WF_NOTIFY_FAIL,
    ];

    /**
     * @var WorkHandler 工作流头节点处理程序，用来启动职责链的调用
     */
    private $head;
    /**
     * @var WorkHandler 尾节点处理程序，用来添加新的处理程序
     */
    private $tail;
    /**
     * @var Task 工作流对应的任务
     */
    private $task;
    /**
     * @var int 工作流当前执行状态
     */
    private $currentStatus;
    // 该工作流能够处理的状态（节点）列表
    private $handleStatus = [];

    private function __construct(Task $task)
    {
        $this->task = $task;
        $this->currentStatus = self::WF_INIT;
    }

    /**
     * 工作流对应的任务
     */
    public function task(): Task
    {
        return $this->task;
    }

    /**
     * 工作流当前状态
     */
    public function status(): int
    {
        return $this->currentStatus;
    }

    /**
     * 启动工作流
     */
    public function start()
    {
        if ($this->currentStatus != self::WF_INIT) {
            return;
        }
        
        $this->notify(self::FIRST_STATUS);
    }

    /**
     * 通知工作流执行
     * 由于有些步骤可能是在单独的子协程或者 task 进程中执行的，只能通过调用此方法异步通知来告知工作流引擎当前是什么进度
     */
    public function notify(int $workStatus, string $msg = '')
    {
        $this->currentStatus = $workStatus;
        if (!in_array($workStatus, $this->handleStatus)) {
            // 没有处理程序处理该状态，结束工作流
            return $this->finishWorkFlow($workStatus, $msg);
        }

        $this->handle($workStatus);
    }

    /**
     * 由于 WorkFlow 和 WorkHandler 之间存在双向（循环）引用，因而需要手动销毁工作流，解除循环引用
     */
    public function destroy()
    {
        $this->head = $this->tail = $this->task = null;
    }

    /**
     * 创建一个新的工作流
     */
    public static function newWorkFlow(Task $task): WorkFlow
    {
        $workFlow = new self($task);

        // 添加节点处理程序
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
            // 修改任务状态
            Container::get(TaskService::class)->switchStatus($task, !in_array($status, self::FAILED_ENDS) ? Task::STATUS_SUC : Task::STATUS_FAILED, $msg);
            Container::get(LoggerInterface::class)->info("任务处理结束：{$task->id()}，任务状态：{$task->status()}，msg：{$msg}");
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error("任务处理结束：{$task->id()}，任务状态：{$task->status()}，msg：{$msg}。但切换状态失败，异常：" . $e->getMessage() . "。trace：" . $e->getTraceAsString());
        }
    }

    /**
     * 执行工作流节点
     */
    protected function handle(int $workStatus)
    {
        $this->head->handle($workStatus);
    }

    /**
     * 添加节点处理程序
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
