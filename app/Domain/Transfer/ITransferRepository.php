<?php

namespace App\Domain\Transfer;

interface ITransferRepository
{
    /**
     * Save a new download ticket
     */
    public function saveDownloadTicket(DownloadTicket $ticket);

    /**
     * Get a download ticket by ID
     */
    public function getDownloadTicket(string $ticketId): ?DownloadTicket;

    /**
     * Save task download count
     */
    public function saveDownloadTimer(DownloadTimer $timer);

    /**
     * Get task download count
     */
    public function getDownloadTimer(string $taskId): ?DownloadTimer;
}
