<?php

namespace App\Domain\Transfer;

use App\ErrCode;
use EasySwoole\EasySwoole\Config;
use OSS\OssClient;
use WecarSwoole\Exceptions\Exception;

class Upload
{
    /**
     * 上传文件到远程存储
     * 使用阿里云的 OSS 存储
     * @param string $localFile 本地文件名称（绝对名称）
     * @param string $taskId 任务编号，内部根据此参数生成远程文件名称
     */
    public function upload(string $localFile, string $taskId, string $downloadName)
    {
        if (!is_readable($localFile)) {
            throw new Exception("本地目标文件不存在", ErrCode::FILE_OP_FAILED);
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
