<?php

namespace App\Domain\Transfer;

use App\Domain\Source\SourceService;
use App\Domain\Target\TargetService;
use App\Domain\Task\Task;
use App\ErrCode;
use App\Foundation\File\LocalFile;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Utility\Random;
use WecarSwoole\Exceptions\Exception;

/**
 * Data Transfer Service: handles uploading and downloading of target files
 */
class TransferService
{
    private $transferRepository;
    private $sourceService;
    private $targetService;

    public function __construct(ITransferRepository $transferRepository, SourceService $sourceService, TargetService $targetService)
    {
        $this->transferRepository = $transferRepository;
        $this->sourceService = $sourceService;
        $this->targetService = $targetService;
    }

    /**
     * Upload local target file to remote storage
     */
    public function upload(Task $task)
    {
        (new Upload())->upload($task->target()->targetFileName(), $task->id(), $task->target()->downloadFileName());

        // Delete local target files
        LocalFile::deleteDir($task->target()->getBaseDir());
    }

    /**
     * Download target file from remote or local storage
     * @return string Local file name
     */
    public function download(Task $task, bool $isValidate = true): string
    {
        if ($isValidate) {
            $this->checkDownloadValidity($task);
        }

        $downloadedFile = (new Download())->pull($task->id(), $task->target()->targetFileName());
        $this->incrDownloadTimes($task->id());

        return $downloadedFile;
    }

    /**
     * Synchronous download
     * @return string Local file name
     */
    public function syncDownload(Task $task): string
    {
        // Fetch source data
        $this->sourceService->fetch($task->source(), $task->target());
        // Generate target data
        $this->targetService->generate($task->source(), $task->target(), false);
        // Download
        return $this->download($task, false);
    }

    /**
     * Generate a temporary download URL
     * @deprecated No longer used; use buildDownloadUrlNew instead
     */
    public function buildDownloadUrl(string $taskId, string $url): string
    {
        $ticket = new DownloadTicket(Random::character(64), $taskId);
        $this->transferRepository->saveDownloadTicket($ticket);

        return $url . (strpos($url, '?') === false ? '?' : '&') . "ticket={$ticket->ticket()}";
    }

    /**
     * Generate a temporary download URL
     */
    public function buildDownloadUrlNew(Task $task): string
    {
        return (new Download())->getTmpDownloadUrl($task->id(), $task->target()->targetFileName());
    }

    /**
     * Validate the download request to prevent malicious attacks
     */
    private function checkDownloadValidity(Task $task)
    {
        $taskId = $task->id();

        $limitFor10min = Config::getInstance()->getConf('download_10m_limit');
        $downloadExpire = Config::getInstance()->getConf('download_expire');

        if ($task->finishedTime() + $downloadExpire < time()) {
            throw new Exception("download fail:task expired:$taskId", ErrCode::DOWNLOAD_FAILED);
        }

        $downloadTimer = $this->transferRepository->getDownloadTimer($taskId);

        if ($downloadTimer && $downloadTimer->times() >= $limitFor10min) {
            throw new Exception("download fail:operate too frequently,taskID:{$taskId}", ErrCode::DOWNLOAD_FAILED);
        }
    }

    /**
     * Record task download count
     */
    private function incrDownloadTimes(string $taskId)
    {
        if (!$downloadTimer = $this->transferRepository->getDownloadTimer($taskId)) {
            $downloadTimer = new DownloadTimer($taskId);
        }

        $downloadTimer->increase();

        $this->transferRepository->saveDownloadTimer($downloadTimer);
    }
}
