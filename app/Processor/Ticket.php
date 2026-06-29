<?php

namespace App\Processor;

use EasySwoole\EasySwoole\Config;
use Psr\Log\LoggerInterface;
use Swoole\Coroutine\Channel;
use WecarSwoole\Container;

/**
 * 限流票据
 */
final class Ticket
{
    private static $channels = [];
    private static $ticketsNum = [];

    /**
     * 获取票据
     */
    public static function get(string $group)
    {
        if (!isset(self::$channels[$group])) {
            self::$channels[$group] = new Channel(Config::getInstance()->getConf("task_concurrent_limit") ?: 20);
        }

        self::$channels[$group]->push(1, 3600);
        self::tick($group, 1);

        // 票据安全性检测：如果票据快用完了，则要发告警通知（一般可能是某些异常任务长时间占用票据）
        if (Ticket::remain($group) <= 1) {
            Container::get(LoggerInterface::class)->warning("下载中心{$group}票据快用完，请检查是否存在异常任务处理");
        }
    }

    /**
     * 归还票据
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
     * 还剩多少票据
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
