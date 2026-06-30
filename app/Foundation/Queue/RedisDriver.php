<?php

namespace App\Foundation\Queue;

use EasySwoole\Queue\Job;
use EasySwoole\Queue\QueueDriverInterface;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;
use WecarSwoole\RedisFactory;

/**
 * Message queue: Redis driver
 */
class RedisDriver implements QueueDriverInterface
{
    protected $redis;
    protected $queueName;

    public function __construct(string $queueName, string $redisAlias = 'queue')
    {
        $this->redis = RedisFactory::build($redisAlias);
        $this->queueName = $queueName;
    }
    
    /**
     * No retry on enqueue failure; a background task will re-enqueue later
     */
    public function push(Job $job): bool
    {
        return $this->redis->lPush($this->redisKey(), json_encode($job->getJobData()));
    }

    /**
     * Dequeue must catch exceptions to prevent them from interrupting the queue listener
     */
    public function pop(float $timeout = 3.0): ?Job
    {
        try {
            if ($data = json_decode($this->redis->rPop($this->redisKey()), true)) {
                $job = new Job();
                $job->setJobData($data);
                return $job;
            }
        } catch (\Exception $e) {
            Container::get(LoggerInterface::class)->critical("dequeue fail:{$e->getMessage()}");
        }

        return null;
    }

    public function size():?int
    {
        return $this->redis->lLen($this->redisKey());
    }

    protected function redisKey(): string
    {
        return "download-queue-{$this->queueName}";
    }
}
