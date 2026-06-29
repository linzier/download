<?php

namespace App\Processor;

use App\Domain\Task\ITaskRepository;
use EasySwoole\EasySwoole\Config;
use EasySwoole\Queue\Job;
use App\Foundation\Queue\Queue;
use EasySwoole\Queue\Consumer;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;

/**
 * 队列监听
 */
class QueueListener
{
    private static $consumer;

    public static function listen()
    {
        /**
         * task 队列监听
         */
        self::consumer()->listen(function (Job $job) {
            // data 格式：['task_id' => '13112sdas', 'enqueue_time' => 23234223423]
            $data = $job->getJobData();
            if (!$data || !isset($data['task_id'])) {
                return;
            }

            if (!$task = Container::get(ITaskRepository::class)->getTaskById($data['task_id'])) {
                Container::get(LoggerInterface::class)->error("处理任务失败：任务不存在：{$data['task_id']}");
                return;
            }
            // 交给任务管理器处理
            TaskManager::getInstance()->process($task);
        }, 1);
    }

    /**
     * 停止 task 队列监听
     */
    public static function stop()
    {
        self::consumer()->stopListen();
    }

    private static function consumer(): Consumer
    {
        if (!self::$consumer) {
            self::$consumer = Queue::instance(Config::getInstance()->getConf('task_queue'))->consumer();
        }

        return self::$consumer;
    }
}
