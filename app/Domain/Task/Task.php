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
 * Task class
 */
class Task extends Entity
{
    // Pending (not enqueued)
    public const STATUS_TODO = 1;
    // Enqueued
    public const STATUS_ENQUEUED = 2;
    // Processing (dequeued and being processed)
    public const STATUS_DOING = 3;
    // Processing succeeded
    public const STATUS_SUC = 4;
    // Processing failed (retryable; if retry limit is exceeded, status transitions to STATUS_ERR)
    public const STATUS_FAILED = 5;
    // Processing failed, non-retryable
    public const STATUS_ERR = 6;
    // Expired (this status is computed programmatically and is not persisted to the database)
    public const STATUS_EXPIRED = 100;
    // Maximum number of processing attempts
    public const MAX_RETRY_NUM = 3;

    /**
     * State transition table (lookup table implementation of the state machine)
     * The first dimension key represents the current state, the second dimension key represents the new state,
     * and the second dimension value indicates whether the transition is allowed.
     * (Note: state values start from 1, while array indices start from 0, so subtract 1 from the state value.)
     * (Self-transitions such as state a -> a are considered allowed; in practice no transition occurs.)
     */
    private const STATUS_TRANS_MAP = [
        [true, true, true, false, false, false],
        [true, true, true, false, false, false],
        [true, false, true, true, true, true],
        [false, false, false, true, false, false],
        [true, false, false, false, true, true],
        [false, false, false, false, false, true],
    ];

    // Task ID
    protected $id;
    // Task name
    protected $name;
    // Owning project
    protected $project;
    // Data source
    protected $source;
    // Target file
    protected $target;
    // Callback notification URI
    protected $callback;
    // Operator ID
    protected $operator;
    // Merchant
    protected $merchant;
    // Task execution time limit
    protected $maxExecTime;
    // Task creation time
    protected $createTime;
    // Last task execution time
    protected $lastExecTime;
    // Task success completion time
    protected $finishedTime;
    // Last status change time
    protected $lastChangeStatusTime;
    // Last enqueue time
    protected $lastEnqueueTime;
    // Task status
    protected $status;
    // Number of processing attempts (including the first attempt)
    protected $retryNum;
    // Failure reason
    protected $failedReason;
    // Whether this is a synchronous task
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

        // For synchronous tasks, set status directly to suc
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
     * Change task status
     */
    public function switchStatus(int $newStatus, string $failedReason = '')
    {
        // If the new status is retryable failure, check whether the retry limit has been reached; if so, change to non-retryable failure
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
        // Lookup table indices start from 0, so subtract 1 from the state value
        $newPos = $newStatus - 1;
        $oldPos = $this->status - 1;

        if (!isset(self::STATUS_TRANS_MAP[$oldPos][$newPos])) {
            throw new Exception("Invalid status value: {$newStatus}", ErrCode::INVALID_STATUS_OP);
        }

        $canTrans = self::STATUS_TRANS_MAP[$oldPos][$newPos];

        if (!$canTrans) {
            throw new Exception("Invalid status transition: {$this->status} -> {$newStatus}", ErrCode::INVALID_STATUS_OP);
        }
    }
}
