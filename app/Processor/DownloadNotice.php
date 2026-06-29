<?php

namespace App\Processor;

use App\Domain\Task\ITaskRepository;
use App\Domain\Task\Task;
use App\ErrCode;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Coroutine;
use WecarSwoole\Container;

/**
 * 通过 web socket 监听任务下载情况，下载完成后通知客户端
 * Class ScanNotice
 * @package App\Process
 */
class DownloadNotice
{
    private const MT_UNKNOW = 0;
    private const MT_DOWNLOAD_LOOP = 1;

    public static function watch(int $fd, string $message)
    {
        list($msgType, $taskId) = self::decodeMsg($message);
        // 仅支持 download 类型
        if ($msgType != self::MT_DOWNLOAD_LOOP || !$taskId) {
            return;
        }

        try {
            /**
             * 循环检查，4s 一次
             * 最多执行 2 小时
             */
            $server = ServerManager::getInstance()->getSwooleServer();
            $repos = Container::get(ITaskRepository::class);
            $usedTime = 0;
            while ($usedTime < 7200 && $server->isEstablished($fd)) {
                if (!$status = $repos->getTaskStatus($taskId)) {
                    return self::notify($fd, json_encode(['code' => ErrCode::TASK_NOT_EXISTS, 'msg' => '任务不存在', 'data' => []]));
                }

                if ($status == Task::STATUS_SUC || $status == Task::STATUS_ERR) {
                    return self::notify(
                        $fd,
                        json_encode(
                            [
                                'code' => ErrCode::OK,
                                'msg' => '任务处理' . ($status == Task::STATUS_SUC ? '成功' : '失败'),
                                'data' => ['status' => $status]
                            ]
                        )
                    );
                }

                Coroutine::sleep(4);
                $usedTime += 4;
            }

            self::notify($fd, json_encode(['code' => ErrCode::ERROR, 'msg' => '任务处理异常，请重试', 'data' => []]));
        } catch (\Exception $e) {
            self::notify($fd, json_encode(['status' => $e->getCode(), 'msg' => $e->getMessage(), 'data' => []]));
        }
    }

    /**
     * 通知客户端
     */
    protected static function notify(int $fd, string $message)
    {
        $server = ServerManager::getInstance()->getSwooleServer();

        if (!$server->isEstablished($fd)) {
            echo "client closed\n";
            return;
        }

        $server->push($fd, $message);
    }

    protected static function decodeMsg(string $msg): array
    {
        $data = explode('|', $msg);
        if (count($data) != 2) {
            return [self::MT_UNKNOW, ''];
        }

        switch (strtolower($data[0])) {
            case 'download':
                return [self::MT_DOWNLOAD_LOOP, $data[1]];
            default:
                return [self::MT_UNKNOW, ''];
        }
    }
}
