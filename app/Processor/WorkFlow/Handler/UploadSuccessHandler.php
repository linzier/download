<?php

namespace App\Processor\WorkFlow\Handler;

use App\Processor\WorkFlow\WorkFlow;
use EasySwoole\EasySwoole\Config;
use WecarSwoole\Client\API;
use WecarSwoole\Container;
use WecarSwoole\Util\Url;
use Psr\Log\LoggerInterface;

/**
 * Upload success handler
 */
class UploadSuccessHandler extends WorkHandler
{
    public function handleStatus(): int
    {
        return WorkFlow::WF_UPLOAD_SUC;
    }

    /**
     * Notify the client
     */
    protected function exec()
    {
        try {
            if ($callback = $this->task()->callbackURI()->url()) {
                $conf = Config::getInstance();
                API::simpleInvoke(
                    $callback,
                    'POST',
                    [
                        'task_id' => $this->task()->id(),
                        'download_url' => Url::assemble($conf->getConf('backend_download_url'), $conf->getConf('base_url'), ['task_id' => $this->task()->id()])
                    ],
                    'weicheche'
                );
            }

            $this->notify(WorkFlow::WF_NOTIFY_DONE);
        } catch (\Throwable $e) {
            Container::get(LoggerInterface::class)->error($e->getMessage() . "taskid:" . $this->workFlow->task()->id(), ['code' => $e->getCode(), 'trace' => $e->getTraceAsString()]);
            $this->notify(WorkFlow::WF_NOTIFY_FAIL, $e->getMessage());
        }
    }
}
