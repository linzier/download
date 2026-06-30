<?php

namespace App\Processor;

use App\Domain\Task\ITaskRepository;
use App\Domain\Task\Task;
use App\ErrCode;
use EasySwoole\EasySwoole\ServerManager;
use Swoole\Coroutine;
use WecarSwoole\Container;

/**
 * Monitors task download progress via WebSocket and notifies the client upon completion.
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
        // Only supports download type
        if ($msgType != self::MT_DOWNLOAD_LOOP || !$taskId) {
            return;
        }

        try {
            /**
             * Poll every 4 seconds
             * Maximum duration: 2 hours
             */
            $server = ServerManager::getInstance()->getSwooleServer();
            $repos = Container::get(ITaskRepository::class);
            $usedTime = 0;
            while ($usedTime < 7200 && $server->isEstablished($fd)) {
                if (!$status = $repos->getTaskStatus($taskId)) {
                    return self::notify($fd, json_encode(['code' => ErrCode::TASK_NOT_EXISTS, 'msg' => 'Task not found', 'data' => []]));
                }

                if ($status == Task::STATUS_SUC || $status == Task::STATUS_ERR) {
                    return self::notify(
                        $fd,
                        json_encode(
                            [
                                'code' => ErrCode::OK,
                                'msg' => 'Task ' . ($status == Task::STATUS_SUC ? 'succeeded' : 'failed'),
                                'data' => ['status' => $status]
                            ]
                        )
                    );
                }

                Coroutine::sleep(4);
                $usedTime += 4;
            }

            self::notify($fd, json_encode(['code' => ErrCode::ERROR, 'msg' => 'Task processing error, please retry', 'data' => []]));
        } catch (\Exception $e) {
            self::notify($fd, json_encode(['status' => $e->getCode(), 'msg' => $e->getMessage(), 'data' => []]));
        }
    }

    /**
     * Notify the client
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
