<?php

namespace App\Domain\Task;

use App\Domain\Target\Target;
use App\Domain\Project\Project;
use App\Domain\Source\ISource;
use App\Domain\URI;
use App\ErrCode;
use WecarSwoole\Entity;
use WecarSwoole\Exceptions\Exception;

/**
 * 任务类
 */
class Task extends Entity
{
    // 待处理（未入列）
    public const STATUS_TODO = 1;
    // 已入列
    public const STATUS_ENQUEUED = 2;
    // 处理中（已出列处理）
    public const STATUS_DOING = 3;
    // 处理成功
    public const STATUS_SUC = 4;
    // 处理失败（该失败可重试，重试次数超限则会转入 STATUS_ERR）
    public const STATUS_FAILED = 5;
    // 处理失败，该失败不可重试
    public const STATUS_ERR = 6;
    // 已过期（该状态是程序算出来的，不会写入数据库）
    public const STATUS_EXPIRED = 100;
    // 最大处理次数
    public const MAX_RETRY_NUM = 3;

    /**
     * 状态转换表（状态机的查找表实现）
     * 二维数组的第一维的 key 表示当前状态，第二维的 key 表示新状态，第二维的 value 表示是否允许该状态转换
     * (注意状态的值是从 1 开始，而数组下标是从 0 开始，即将状态值 - 1)
     * （自己到自己如状态 a -> a 被认为是允许的，实际是没有任何转换）
     */
    private const STATUS_TRANS_MAP = [
        [true, true, true, false, false, false],
        [true, true, true, false, false, false],
        [true, false, true, true, true, true],
        [false, false, false, true, false, false],
        [true, false, false, false, true, true],
        [false, false, false, false, false, true],
    ];

    // 任务 id
    protected $id;
    // 任务名称
    protected $name;
    // 所属的项目
    protected $project;
    // 数据源
    protected $source;
    // 目标文件
    protected $target;
    // 回调通知 uri
    protected $callback;
    // 操作者编号
    protected $operator;
    // 商户
    protected $merchant;
    // 任务执行时限
    protected $maxExecTime;
    // 任务创建时间
    protected $createTime;
    // 任务最后处理时间
    protected $lastExecTime;
    // 任务执行成功的时间
    protected $finishedTime;
    // 最后状态修改时间
    protected $lastChangeStatusTime;
    // 最后入列时间
    protected $lastEnqueueTime;
    // 任务状态
    protected $status;
    // 处理次数（包括第一次处理）
    protected $retryNum;
    // 处理失败原因
    protected $failedReason;
    // 是否同步任务
    protected $isSync;

    public function __construct(
        string $id,
        string $name,
        Project $project,
        ISource $source,
        Target $target,
        URI $callback = null,
        string $operator = '',
        int $maxExecTime = 0,
        int $isSync = 0,
        Merchant $merchant
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->project = $project;
        $this->source = $source;
        $this->target = $target;
        $this->callback = $callback;
        $this->operator = $operator;
        $this->createTime = time();
        $this->lastExecTime = 0;
        $this->finishedTime = 0;
        $this->lastChangeStatusTime = 0;
        $this->lastEnqueueTime = 0;
        $this->status = self::STATUS_TODO;
        $this->retryNum = 0;
        $this->failedReason = '';
        $this->maxExecTime = $maxExecTime;
        $this->isSync = $isSync;
        $this->merchant = $merchant;

        // 同步任务直接将状态设置为 suc
        if ($isSync) {
            $this->status = self::STATUS_SUC;
            $this->retryNum = 1;
            $this->lastExecTime = $this->finishedTime = $this->lastChangeStatusTime = $this->createTime;
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function project(): Project
    {
        return $this->project;
    }

    public function source(): ISource
    {
        return $this->source;
    }

    public function target(): Target
    {
        return $this->target;
    }

    public function callbackURI(): ?URI
    {
        return $this->callback;
    }

    public function maxExecTime(): int
    {
        return $this->maxExecTime;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function isSuccessed(): bool
    {
        return $this->status() === self::STATUS_SUC;
    }

    public function finishedTime(): int
    {
        return $this->finishedTime;
    }

    public function isSync(): bool
    {
        return boolval($this->isSync);
    }

    /**
     * 更改任务状态
     */
    public function switchStatus(int $newStatus, string $failedReason = '')
    {
        // 如果新状态是可重试失败，则要检查重试次数是否已经用完，如用完，则将状态改为不可重试的失败
        if ($newStatus === self::STATUS_FAILED && $this->retryNum >= self::MAX_RETRY_NUM) {
            $newStatus = self::STATUS_ERR;
        }

        $this->validateStatusChange($newStatus);

        $time = time();
        $this->status = $newStatus;
        $this->lastChangeStatusTime = $time;

        switch ($newStatus) {
            case self::STATUS_ENQUEUED:
                $this->lastEnqueueTime = $time;
                break;
            case self::STATUS_DOING:
                $this->retryNum++;
                $this->lastExecTime = $time;
                break;
            case self::STATUS_SUC:
                $this->finishedTime = $time;
                break;
        }

        $this->failedReason = $failedReason ?: '';
    }

    private function validateStatusChange(int $newStatus)
    {
        // 查找表数组下表从 0 开始，要用状态值 - 1
        $newPos = $newStatus - 1;
        $oldPos = $this->status - 1;

        if (!isset(self::STATUS_TRANS_MAP[$oldPos][$newPos])) {
            throw new Exception("非法的状态值：{$newStatus}", ErrCode::INVALID_STATUS_OP);
        }

        $canTrans = self::STATUS_TRANS_MAP[$oldPos][$newPos];

        if (!$canTrans) {
            throw new Exception("非法的状态切换：{$this->status} -> {$newStatus}", ErrCode::INVALID_STATUS_OP);
        }
    }
}
