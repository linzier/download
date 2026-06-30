<?php

namespace App\Processor;

use App\Bootstrap;
use App\Domain\Task\ITaskRepository;
use App\Foundation\File\LocalFile;
use App\Processor\Monitor\QueueMonitor;
use App\Processor\Monitor\TaskRetry;
use EasySwoole\Component\Process\AbstractProcess;
use EasySwoole\EasySwoole\Config;
use WecarSwoole\Container;
use WecarSwoole\Util\File;
use Swoole\Timer;
use Closure;
use Psr\Log\LoggerInterface;
use Swoole\Process;
use EasySwoole\Component\Timer as EsTimer;
use Swoole\Event;

/**
 * Background daemon process that handles failed task retries and data archival.
 * Class Defender
 */
class Defender extends AbstractProcess
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    private $swProcess;

    public function __start(Process $process)
    {
        $this->swProcess = $process;
        parent::__start($process);
    }

    public function run($arg)
    {
        // EasySwoole's AbstractProcess has a bug: it catches SIGTERM without terminating the current process,
        // making it impossible to stop the process and thereby preventing the entire service from being stopped via SIGTERM.
        // Override the signal handler registered in AbstractProcess to handle termination properly.
        Process::signal(SIGTERM, function () {
            Process::signal(SIGTERM, null);// Unregister this signal handler first
            swoole_event_del($this->swProcess->pipe);// Remove event loop on the pipe
            EsTimer::getInstance()->clearAll();// Clear all timers
            Event::exit();// Exit event loop
            Process::kill($this->getPid(), SIGTERM);// Send SIGTERM again to terminate the current process
        });

        Bootstrap::boot();

        $this->logger = Container::get(LoggerInterface::class);
        $this->logger->info('Starting daemon process');

        // Logic that runs only on the master server
        if (self::isMaster()) {
            $this->logger->info("Starting master server daemon");
            self::addMasterFlag();
            $this->masterDefender();
        }

        // Every 30 minutes, clean up unused files in the directory
        Timer::tick(1800000, Closure::fromCallable([$this, 'clearDir']));
    }

    private static function isMaster(): bool
    {
        // Check environment variable first
        $master = getenv('WECARSWOOLE_MASTER');
        if ($master && trim($master) == 1) {
            return true;
        }

        // Check constant
        if (defined('WECARSWOOLE_MASTER') && WECARSWOOLE_MASTER) {
            return true;
        }

        // Check IP configuration (legacy compatibility)
        $masterIp = Config::getInstance()->getConf('master_server');
        if ($masterIp && in_array($masterIp, swoole_get_local_ip())) {
            return true;
        }

        return false;
    }

    public function onShutDown()
    {
        // nothing
    }

    public function onReceive(string $str)
    {
        // nothing
    }

    public static function addMasterFlag()
    {
        file_put_contents(File::join(STORAGE_ROOT, 'temp/master_defender.txt'), date('Y-m-d H:i:s'));
    }

    public static function removeMasterFlag()
    {
        if (file_exists($file = File::join(STORAGE_ROOT, 'temp/master_defender.txt'))) {
            unlink($file);
        }
    }

    private function masterDefender()
    {
        // Every 15 seconds, retry failed tasks
        Timer::tick(15000, Closure::fromCallable([TaskRetry::getInstance(), 'watch']));
        // Every 30 seconds, monitor queue status
        Timer::tick(30000, Closure::fromCallable([QueueMonitor::getInstance(), 'watch']));
        // Every 3 hours, archive task data
        Timer::tick(10800000, Closure::fromCallable([$this, 'fileData']));
    }

    /**
     * Clean up directories with last modified time older than 6 hours
     */
    private function clearDir()
    {
        $dir = Config::getInstance()->getConf('local_file_base_dir');

        try {
            foreach (scandir($dir) as $subDir) {
                if ($subDir == '.' || $subDir == '..') {
                    continue;
                }
                
                $realDir = File::join($dir, $subDir);
                if (!is_dir($realDir) || time() - filemtime($realDir) < 21600) {
                    continue;
                }
    
                LocalFile::deleteDir($realDir);
            }
        } catch (\Exception $e) {
            $this->logger->error("Daemon process exception. msg:{$e->getMessage()},trace:" . $e->getTraceAsString());
        }
    }

    /**
     * Archive data older than 3 months
     */
    private function fileData()
    {
        $hour = intval(date('G'));

        // Only process between 0:00 and 6:00
        if ($hour > 6) {
            return;
        }

        $optimize = mt_rand(0, 100) > 95 ? true : false;

        try {
            Container::get(ITaskRepository::class)->fileTask(time() - 86400 * 30 * 3, $optimize);
        } catch (\Exception $e) {
            $this->logger->error("Daemon process exception. msg:{$e->getMessage()},trace:" . $e->getTraceAsString());
        }
    }
}
