<?php

namespace App\Domain\Transfer;

interface ITransferRepository
{
    /**
     * 添加新 ticket
     */
    public function saveDownloadTicket(DownloadTicket $ticket);

    /**
     * 根据 id 查询 ticket
     */
    public function getDownloadTicket(string $ticketId): ?DownloadTicket;

    /**
     * 保存任务下载次数
     */
    public function saveDownloadTimer(DownloadTimer $timer);

    /**
     * 查询任务下载次数
     */
    public function getDownloadTimer(string $taskId): ?DownloadTimer;
}
