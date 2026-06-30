<?php

namespace EasySwoole\EasySwoole;

use App\Bootstrap;
use App\Processor\Defender;
use App\Processor\DownloadNotice;
use App\Processor\QueueListener;
use EasySwoole\EasySwoole\Swoole\EventRegister;
use EasySwoole\EasySwoole\AbstractInterface\Event;
use EasySwoole\Component\Context\ContextManager;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;
use EasySwoole\Component\Di;
use WecarSwoole\CronTabUtil;
use WecarSwoole\Process\ApolloWatcher;
use WecarSwoole\Process\HotReload;
use WecarSwoole\RequestId;

class EasySwooleEvent implements Event
{
    public static function initialize()
    {
        date_default_timezone_set('Asia/Shanghai');

        // HTTP controller namespace
        Di::getInstance()->set(SysConst::HTTP_CONTROLLER_NAMESPACE, 'App\\Http\\Controllers\\');
    }

    /**
     * @param EventRegister $register
     * @throws \WecarSwoole\Exceptions\ConfigNotFoundException
     */
    public static function mainServerCreate(EventRegister $register)
    {
        $server = ServerManager::getInstance()->getSwooleServer();

        // Set up WebSocket message handler
        $server->on("message", function ($server, $frame) {
            DownloadNotice::watch($frame->fd, $frame->data);
        });

        // Hot reload (non-production environments only)
        if (Core::getInstance()->isDev()) {
            $server->addProcess(
                (new HotReload(
                    'HotReload',
                    [
                        'disableInotify' => true,
                        'monitorDirs' => [
                            EASYSWOOLE_ROOT . '/app',
                            EASYSWOOLE_ROOT . '/mock',
                            CONFIG_ROOT
                        ]
                    ]
                ))->getProcess()
            );
        }

        // Worker process startup script
        $register->add(EventRegister::onWorkerStart, function ($server) {
            ini_set("memory_limit", "4096M");
            Bootstrap::boot();

            // Start queue listener (worker process only)
            if (!$server->taskworker) {
                QueueListener::listen();
            }
        });

        // Attempt to clean up master flag file on server shutdown
        $register->add(EventRegister::onShutdown, function ($server) {
            Defender::removeMasterFlag();
        });

        // Apollo configuration change watcher
        $server->addProcess((new ApolloWatcher())->getProcess());
        // Task defender process
        $server->addProcess((new Defender())->getProcess());

        CronTabUtil::register();
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     * @throws \EasySwoole\Component\Context\Exception\ModifyError
     */
    public static function onRequest(Request $request, Response $response): bool
    {
        // Set request ID
        ContextManager::getInstance()->set('wcc-request-id', new RequestId($request));

        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterRequest() method.
    }
}
