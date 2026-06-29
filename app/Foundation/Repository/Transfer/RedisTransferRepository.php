<?php

namespace App\Foundation\Repository\Transfer;

use App\Domain\Transfer\DownloadTicket;
use App\Domain\Transfer\DownloadTimer;
use App\Domain\Transfer\ITransferRepository;
use ReflectionClass;
use WecarSwoole\RedisFactory;
use WecarSwoole\Repository\Repository;

class RedisTransferRepository extends Repository implements ITransferRepository
{
    private $redis;

    public function __construct()
    {
        $this->redis = RedisFactory::build('main');
    }

    public function saveDownloadTicket(DownloadTicket $ticket)
    {
        $info = [
            'id' => $ticket->ticket(),
            'task_id' => $ticket->taskId(),
            'create_time' => $ticket->createTime,
        ];

        $this->redis->set($this->getTicketRedisKey($ticket->ticket()), json_encode($info), 350);
    }

    public function getDownloadTicket(string $ticketId): ?DownloadTicket
    {
        if (!$info = $this->redis->get($this->getTicketRedisKey($ticketId))) {
            return null;
        }
        
        $info = json_decode($info, true);

        $ticket = (new ReflectionClass(DownloadTicket::class))->newInstanceWithoutConstructor();
        $ticket->id = $info['id'];
        $ticket->taskId = $info['task_id'];
        $ticket->createTime = $info['create_time'];

        return $ticket;
    }

    /**
     * 保存任务下载次数
     */
    public function saveDownloadTimer(DownloadTimer $timer)
    {
        $info = [
            'task_id' => $timer->taskId,
            'create_time' => $timer->createTime,
            'times' => $timer->times,
        ];

        $this->redis->set($this->getTimerRedisKey($timer->taskId), json_encode($info), 650);
    }

    /**
     * 查询任务下载次数
     */
    public function getDownloadTimer(string $taskId): ?DownloadTimer
    {
        if (!$info = $this->redis->get($this->getTimerRedisKey($taskId))) {
            return null;
        }
        
        $info = json_decode($info, true);

        $timer = (new ReflectionClass(DownloadTimer::class))->newInstanceWithoutConstructor();
        $timer->taskId = $info['task_id'];
        $timer->createTime = $info['create_time'];
        $timer->times = $info['times'];

        return $timer;
    }

    private function getTicketRedisKey(string $ticketId): string
    {
        return 'download_ticket_' . md5($ticketId);
    }

    private function getTimerRedisKey(string $taskId): string
    {
        return 'download_timer_' . md5($taskId);
    }
}
