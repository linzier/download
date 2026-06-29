<?php

namespace App\Domain\Transfer;

use App\ErrCode;
use EasySwoole\EasySwoole\Config;
use OSS\OssClient;
use WecarSwoole\Exceptions\Exception;
use WecarSwoole\Util\File;

class Download
{
    /**
     * 下载文件
     * 如果本地存在，则什么都不做，没有的话再从阿里云 OSS 下载
     * @param string $taskId 任务编号
     * @param string $targetFile 本地原始文件名（未经过压缩的）
     * @return string 真实存在的本地文件名
     */
    public function pull(string $taskId, string $targetFile): string
    {
        $realLocalFile = '';

        if (file_exists($targetFile)) {
            $realLocalFile = $targetFile;
        } elseif (file_exists($zipFile = $this->zipFile($targetFile))) {
            $realLocalFile = $zipFile;
        } else {
            // 从 OSS 拉数据到本地
            $realLocalFile = $this->fetchFileFromOSS($taskId, dirname($targetFile), explode('.', $targetFile)[1]);
        }

        return $realLocalFile;
    }

    /**
     * 获取临时下载 url
     */
    public function getTmpDownloadUrl(string $taskId, string $targetFile): string
    {
        $config = Config::getInstance();
        $client = $this->ossClient();
        $bucket = $config->getConf('oss_bucket');

        $remoteName = $this->getRemoteName($client, $bucket, $taskId, explode('.', $targetFile)[1] ?? 'xlsx');
        $client->setUseSSL(true);
        return $client->signUrl($bucket, $remoteName);
    }

    private function zipFile(string $origFile): string
    {
        $ext = [
            COMPRESS_TYPE_ZIP => 'zip',
        ][Config::getInstance()->getConf('zip_type')];

        return explode('.', $origFile)[0] . '.' . $ext;
    }

    /**
     * 从 OSS 下载文件到本地
     * @return string 下载到本地后在本地的绝对文件名
     */
    private function fetchFileFromOSS(string $taskId, string $localDir, string $ext): string
    {
        $config = Config::getInstance();
        $client = $this->ossClient();
        $bucket = $config->getConf('oss_bucket');
        
        if (!file_exists($localDir)) {
            mkdir($localDir);
            chmod($localDir, 0755);
        }

        $remoteName = $this->getRemoteName($client, $bucket, $taskId, $ext);

        // 下载到本地
        $localFile = File::join($localDir, 'target.' . explode('.', $remoteName)[1]);
        if (!file_exists($localFile)) {
            touch($localFile);
        }

        $client->getObject($bucket, $remoteName, [OssClient::OSS_FILE_DOWNLOAD => $localFile]);

        return $localFile;
    }

    private function ossClient(): OssClient
    {
        $config = Config::getInstance();
        $accessKey = $config->getConf('oss_access_key');
        $accessSecret = $config->getConf('oss_access_secret');
        $endpoint = $config->getConf('oss_endpoint');

        return new OssClient($accessKey, $accessSecret, $endpoint);
    }

    private function getRemoteName(OssClient $client, string $bucket, string $taskId, string $ext): string
    {
        $remoteName = $taskId . '.' . $ext;
        
        if (!$client->doesObjectExist($bucket, $remoteName)) {
            $remoteName = $this->zipFile($taskId);
            if (!$client->doesObjectExist($bucket, $remoteName)) {
                throw new Exception("the download file is not exist(expired or deleted),taskId:$taskId", ErrCode::DOWNLOAD_FAILED);
            }
        }

        return $remoteName;
    }
}
