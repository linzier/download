<?php

namespace App\Domain\Transfer;

use WecarSwoole\Entity;

/**
 * 任务下载计数器
 */
class DownloadTimer extends Entity
{
    const FREQUENCY = 600;

    protected $taskId;
    protected $createTime;
    protected $times;

    public function __construct(string $taskId)
    {
        $this->taskId = $taskId;
        $this->createTime = time();
        $this->times = 0;
    }

    public function increase()
    {
        if (time() - $this->createTime >= self::FREQUENCY) {
            $this->times = 1;
        } else {
            $this->times += 1;
        }
    }

    public function times(): int
    {
        return time() - $this->createTime >= self::FREQUENCY ? 0 : $this->times;
    }
}
