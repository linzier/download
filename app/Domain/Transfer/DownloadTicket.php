<?php

namespace App\Domain\Transfer;

use WecarSwoole\Entity;

/**
 * 生成临时下载 url 用的 ticket
 */
class DownloadTicket extends Entity
{
    const EXPIRE = 300;

    protected $id;
    protected $taskId;
    protected $createTime;

    public function __construct(string $ticket, string $taskId)
    {
        $this->id = $ticket;
        $this->taskId = $taskId;
        $this->createTime = time();
    }

    public function ticket(): string
    {
        return $this->id;
    }

    public function taskId(): string
    {
        return $this->taskId;
    }

    public function isValid(): bool
    {
        return time() - $this->createTime < self::EXPIRE;
    }
}
