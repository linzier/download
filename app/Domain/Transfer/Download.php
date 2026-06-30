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
     * Download file.
     * If the file already exists locally, do nothing; otherwise download it from Alibaba Cloud OSS.
     * @param string $taskId Task ID
     * @param string $targetFile Local original file name (uncompressed)
     * @return string The actual existing local file name
     */
    public function pull(string $taskId, string $targetFile): string
    {
        $realLocalFile = '';

        if (file_exists($targetFile)) {
            $realLocalFile = $targetFile;
        } elseif (file_exists($zipFile = $this->zipFile($targetFile))) {
            $realLocalFile = $zipFile;
        } else {
            // Fetch data from OSS to local
            $realLocalFile = $this->fetchFileFromOSS($taskId, dirname($targetFile), explode('.', $targetFile)[1]);
        }

        return $realLocalFile;
    }

    /**
     * Get a temporary download URL
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
     * Download file from OSS to local
     * @return string Absolute local file path after downloading
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

        // Download to local
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
