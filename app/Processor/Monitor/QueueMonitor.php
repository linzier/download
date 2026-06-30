<?php

namespace App\Processor\Monitor;

use App\Foundation\Queue\Queue;
use EasySwoole\Component\Singleton;
use EasySwoole\EasySwoole\Config;
use Psr\Log\LoggerInterface;
use WecarSwoole\Container;

/**
 * Queue monitor
 * Uses a linked list to record queue size at each check interval
 */
class QueueMonitor
{
    use Singleton;

    private const THRESHOLD_FIVE = 30;
    private const THRESHOLD_FIFTEEN = 20;
    private const THRESHOLD_THIRTY = 10;
    private const T_FIVE = 5;
    private const T_FIFTEEN = 15;
    private const T_THIRTY = 30;

    // Peak queue size
    private $peakSize;
    // Most recent queue size
    private $latestSize;
    // Last check time
    private $lastCheckTime;
    /**
     * Linked list head
     * @var SizeNode
     */
    private $head;
    /**
     * Linked list tail
     * @var SizeNode
     */
    private $tail;
    // 5-minute window pointer
    private $fivePoint;
    // 15-minute window pointer
    private $fifteenPoint;
    // 30-minute window pointer
    private $thirtyPoint;
    private $sizeInfo;

    public function __construct()
    {
        $this->sizeInfo = [
            self::T_FIVE => [0, 0],// Bucket: [total_size, count], average = total_size / count
            self::T_FIFTEEN => [0, 0],
            self::T_THIRTY => [0, 0],
        ];
    }

    public function watch()
    {
        try {
            $size = Queue::instance(Config::getInstance()->getConf('task_queue'))->size();
            if ($size > $this->peakSize) {
                $this->peakSize = $size;
            }
            $this->latestSize = $size;

            $this->addNode(new SizeNode($size, time()));

            // Update bucket data
            $this->updateBucket(self::T_FIVE, $size, 1);
            $this->updateBucket(self::T_FIFTEEN, $size, 1);
            $this->updateBucket(self::T_THIRTY, $size, 1);

            // Calculate averages
            $this->calc();
        } catch (\Exception $e) {
            Container::get(LoggerInterface::class)->critical("Redis connection error: {$e->getMessage()}");
        }
    }

    private function calc()
    {
        // Advance each window pointer
        $this->updatePoint(self::T_FIVE, $this->fivePoint);
        $this->updatePoint(self::T_FIFTEEN, $this->fifteenPoint);
        $this->updatePoint(self::T_THIRTY, $this->thirtyPoint);

        $this->removeExpiredNode();

        // Calculate averages; skip windows with fewer than 3 samples
        $bk = $this->sizeInfo;
        $fiveAvg = $bk[self::T_FIVE][1] < 3 ? 0 : $bk[self::T_FIVE][0] / $bk[self::T_FIVE][1];
        $fifteenAvg = $bk[self::T_FIFTEEN][1] < 3 ? 0 : $bk[self::T_FIFTEEN][0] / $bk[self::T_FIFTEEN][1];
        $thirtyAvg = $bk[self::T_THIRTY][1] < 3 ? 0 : $bk[self::T_THIRTY][0] / $bk[self::T_THIRTY][1];

        // Alert if any window exceeds its threshold
        if ($fiveAvg >= self::THRESHOLD_FIVE || $fifteenAvg >= self::THRESHOLD_FIFTEEN || $thirtyAvg >= self::THRESHOLD_THIRTY) {
            $msg = "Download center task queue load alert (queue length). 5 min: {$fiveAvg}, 15 min: {$fifteenAvg}, 30 min: {$thirtyAvg}, peak: {$this->peakSize}, latest: {$this->latestSize}";
            Container::get(LoggerInterface::class)->critical($msg);
        }
    }

    private function removeExpiredNode()
    {
        // Remove expired nodes (nodes beyond the oldest window pointer)
        while (1) {
            if (!$this->head || !$this->head->next() || $this->head === $this->thirtyPoint) {
                break;
            }

            // Advance head forward
            $this->head = $this->head->next();
        }
    }

    /**
     * Advance the time-window pointer so that all nodes it points to (and those before it)
     * fall within the valid time window.
     */
    private function updatePoint(int $flag, SizeNode &$pointer)
    {
        if (!$pointer) {
            $pointer = $this->head;
            return;
        }

        $currNode = $pointer;
        $now = time();
        while (1) {
            if ($currNode->time() > $now - 60 * $flag || !$currNode->next()) {
                break;
            }

            // Node is outside the window; migrate toward tail and remove its contribution from the bucket
            $this->updateBucket($flag, $currNode->size() * -1, -1);
            $currNode = $currNode->next();
        }

        $pointer = $currNode;
    }

    /**
     * Append a node to the linked list
     */
    private function addNode(SizeNode $node)
    {
        // Append new node to the tail
        if (!$this->head) {
            $this->head = $node;
            $this->tail = $node;
            $this->fivePoint = $node;
            $this->fifteenPoint = $node;
            $this->thirtyPoint = $node;
            return;
        }

        // Advance the tail pointer
        $this->tail->setNext($node);
        $this->tail = $node;
    }

    /**
     * Update bucket statistics
     */
    private function updateBucket(int $flag, int $incrSize, int $incrCount = 1)
    {
        if (!isset($this->sizeInfo[$flag])) {
            return;
        }

        $this->sizeInfo[$flag][0] += $incrSize;
        $this->sizeInfo[$flag][1] += $incrCount;
    }
}
