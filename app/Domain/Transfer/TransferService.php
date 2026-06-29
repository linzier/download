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
 * 数据传输服务，用于处理目标文件的上传、下载
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
     * 将本地目标文件上传到远程存储
     */
    public function upload(Task $task)
    {
        (new Upload())->upload($task->target()->targetFileName(), $task->id(), $task->target()->downloadFileName());

        // 删除本地目标文件
        LocalFile::deleteDir($task->target()->getBaseDir());
    }

    /**
     * 从远程或本地存储下载目标文件
     * @return string 本地文件名称
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
     * 同步下载
     * @return string 本地文件名称
     */
    public function syncDownload(Task $task): string
    {
        // 获取源数据
        $this->sourceService->fetch($task->source(), $task->target());
        // 生成目标数据
        $this->targetService->generate($task->source(), $task->target(), false);
        // 下载
        return $this->download($task, false);
    }

    /**
     * 生成临时下载 url
     * @deprecated 不再使用该方法，使用 buildDownloadUrlNew
     */
    public function buildDownloadUrl(string $taskId, string $url): string
    {
        $ticket = new DownloadTicket(Random::character(64), $taskId);
        $this->transferRepository->saveDownloadTicket($ticket);

        return $url . (strpos($url, '?') === false ? '?' : '&') . "ticket={$ticket->ticket()}";
    }

    /**
     * 生成临时下载 url
     */
    public function buildDownloadUrlNew(Task $task): string
    {
        return (new Download())->getTmpDownloadUrl($task->id(), $task->target()->targetFileName());
    }

    /**
     * 检查下载请求的合法性，防止恶意攻击
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
     * 记录任务下载次数
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
