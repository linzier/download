<?php

namespace App\Domain\Transfer;

use App\ErrCode;
use EasySwoole\EasySwoole\Config;
use OSS\OssClient;
use WecarSwoole\Exceptions\Exception;

class Upload
{
    /**
     * Upload file to remote storage
     * Uses Alibaba Cloud OSS storage
     * @param string $localFile Local file name (absolute path)
     * @param string $taskId Task ID; used internally to generate the remote file name
     */
    public function upload(string $localFile, string $taskId, string $downloadName)
    {
        if (!is_readable($localFile)) {
            throw new Exception("Local target file does not exist", ErrCode::FILE_OP_FAILED);
        }

        $config = Config::getInstance();
        $accessKey = $config->getConf('oss_access_key');
        $accessSecret = $config->getConf('oss_access_secret');
        $endpoint = $config->getConf('oss_endpoint');
        $bucket = $config->getConf('oss_bucket');
        $ext = explode('.', $localFile)[1];
        $remoteName = $taskId . '.' . $ext;
        $downloadName = explode('.', $downloadName)[0] . '.' . $ext;

        $client = new OssClient($accessKey, $accessSecret, $endpoint);
        $client->uploadFile($bucket, $remoteName, $localFile, [
            OssClient::OSS_HEADERS => ['Content-Disposition' => "attachment;filename={$downloadName}"]
        ]);
    }
}
