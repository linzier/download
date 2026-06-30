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
 * Queue Listener
 */
class QueueListener
{
    private static $consumer;

    public static function listen()
    {
        /**
         * Task queue listener
         */
        self::consumer()->listen(function (Job $job) {
            // Data format: ['task_id' => '13112sdas', 'enqueue_time' => 23234223423]
            $data = $job->getJobData();
            if (!$data || !isset($data['task_id'])) {
                return;
            }

            if (!$task = Container::get(ITaskRepository::class)->getTaskById($data['task_id'])) {
                Container::get(LoggerInterface::class)->error("Failed to process task: task not found: {$data['task_id']}");
                return;
            }
            // Delegate to task manager for processing
            TaskManager::getInstance()->process($task);
        }, 1);
    }

    /**
     * Stop task queue listener
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
