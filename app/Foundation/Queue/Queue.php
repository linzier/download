<?php

namespace App\Foundation\Queue;

use EasySwoole\Queue\Queue as EsQueue;

class Queue
{
    private static $map = [];

    public static function instance(string $queueName): EsQueue
    {
        if (!isset(self::$map[$queueName])) {
            self::$map[$queueName] = new EsQueue(new RedisDriver($queueName));
        }

        return self::$map[$queueName];
    }
}
