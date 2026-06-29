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

        // HTTP 控制器命名空间
        Di::getInstance()->set(SysConst::HTTP_CONTROLLER_NAMESPACE, 'App\\Http\\Controllers\\');
    }

    /**
     * @param EventRegister $register
     * @throws \WecarSwoole\Exceptions\ConfigNotFoundException
     */
    public static function mainServerCreate(EventRegister $register)
    {
        $server = ServerManager::getInstance()->getSwooleServer();

        // 设置 web socket 处理程序
        $server->on("message", function ($server, $frame) {
            DownloadNotice::watch($frame->fd, $frame->data);
        });

        // 热重启(仅用在非生产环境)
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

        // worker 进程启动脚本
        $register->add(EventRegister::onWorkerStart, function ($server) {
            ini_set("memory_limit", "4096M");
            Bootstrap::boot();

            // 启动队列监听（仅在 worker 进程启动）
            if (!$server->taskworker) {
                QueueListener::listen();
            }
        });

        // 服务器关闭时试图清理 master flag 文件
        $register->add(EventRegister::onShutdown, function ($server) {
            Defender::removeMasterFlag();
        });

        // Apollo 配置变更监听程序
        $server->addProcess((new ApolloWatcher())->getProcess());
        // 任务守卫程序
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
        // 设置 request id
        ContextManager::getInstance()->set('wcc-request-id', new RequestId($request));

        return true;
    }

    public static function afterRequest(Request $request, Response $response): void
    {
        // TODO: Implement afterRequest() method.
    }
}
