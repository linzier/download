<?php

namespace App\Http\Service;

use App\Domain\Task\ITaskRepository;
use App\Domain\Task\TaskService;
use App\Domain\Transfer\ITransferRepository;
use App\Domain\Transfer\TransferService;
use App\ErrCode;
use App\Foundation\DTO\TaskDTO;
use EasySwoole\Http\Response;
use WecarSwoole\Exceptions\Exception;

/**
 * 下载服务
 * 应用层服务
 */
class DownloadService
{
    private $transferService;
    private $taskService;
    private $taskRepository;
    private $transferRepository;

    public function __construct(
        TransferService $transferService,
        TaskService $taskService,
        ITaskRepository $taskRepository,
        ITransferRepository $transferRepository
    ) {
        $this->transferService = $transferService;
        $this->taskService = $taskService;
        $this->transferRepository = $transferRepository;
        $this->taskRepository = $taskRepository;
    }

    /**
     * 下载文件
     */
    public function download(string $taskId, Response $response)
    {
        if (!$task = $this->taskRepository->getTaskById($taskId)) {
            throw new Exception("任务不存在：{$taskId}", ErrCode::DOWNLOAD_FAILED);
        }

        $localFile = $this->transferService->download($task);
        $this->output($localFile, $task->target()->downloadFileName(), $response);
    }

    /**
     * 通过 ticket 下载资源
     */
    public function downloadWithTicket(string $ticketId, Response $response)
    {
        if (!$ticket = $this->transferRepository->getDownloadTicket($ticketId)) {
            throw new Exception("download fail:ticket not exist or expired", ErrCode::DOWNLOAD_FAILED);
        }

        if (!$ticket->isValid()) {
            throw new Exception("download fail:ticket expired", ErrCode::DOWNLOAD_FAILED);
        }

        $this->download($ticket->taskId(), $response);
    }

    /**
     * 同步下载文件（任务投递和下载一体化，用于下载小文件）
     */
    public function syncDownload(TaskDTO $taskDTO, Response $response)
    {
        $taskDTO->isSync = 1;
        $task = $this->taskService->create($taskDTO);
        $localFile = $this->transferService->syncDownload($task);
        $this->output($localFile, $task->target()->downloadFileName(), $response);
    }

    private function output(string $localFile, string $downloadName, Response $response)
    {
        $downloadName = explode('.', $downloadName)[0] . '.' . explode('.', $localFile)[1];
        
        set_time_limit(0);
        $response->withHeader("Content-type", "application/octet-stream");
        $response->withHeader("Content-Disposition", "attachment; filename=$downloadName");
        $response->withHeader("Cache-Control", "no-cache, must-revalidate");
        $response->withHeader("Pragma", "no-cache");
        $response->withHeader("Content-Length", filesize($localFile));
        $response->withHeader("Content-Transfer-Encoding", "binary");
        $response->sendFile($localFile);
    }
}
