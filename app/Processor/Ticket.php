<?php

namespace App\Processor;

use EasySwoole\EasySwoole\Config;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use WecarSwoole\Container;

/**
 * Rate Limiting Ticket
 */
final class Ticket
{
    private static $channels = [];
    private static $ticketsNum = [];

    /**
     * Acquire a ticket
     */
    public static function get(string $group)
    {
        if (!isset(self::$channels[$group])) {
            self::$channels[$group] = new Channel(Config::getInstance()->getConf("task_concurrent_limit") ?: 20);
        }

        self::$channels[$group]->push(1, 3600);
        self::tick($group, 1);

        // Ticket safety check: if tickets are nearly exhausted, send an alert (typically caused by abnormal tasks holding tickets for too long)
        if (Ticket::remain($group) <= 1) {
            Container::get(LoggerInterface::class)->warning("Download center {$group} tickets nearly exhausted, please check for abnormal task processing");
        }
    }

    /**
     * Return a ticket
     */
    public static function done(string $group)
    {
        if (!isset(self::$channels[$group])) {
            return;
        }

        self::$channels[$group]->pop(0);
        self::tick($group, -1);
    }

    /**
     * How many tickets remain
     */
    private static function remain(string $group): int
    {
        $total = Config::getInstance()->getConf("task_concurrent_limit");
        if (!isset(self::$ticketsNum[$group])) {
            return $total;
        }

        return $total - self::$ticketsNum[$group];
    }

    private static function tick(string $group, $num)
    {
        if (!isset(self::$ticketsNum[$group])) {
            self::$ticketsNum[$group] = 0;
        }

        self::$ticketsNum[$group] += $num;
    }
}
