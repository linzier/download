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
 * 后台守卫程序，执行失败重试、数据归档的任务
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
        // easyswoole 的AbstractProcess存在bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止
        // 此处做终止处理
        // 覆盖掉 AbstractProcess 中的事件注册
        Process::signal(SIGTERM, function () {
            Process::signal(SIGTERM, null);// 先取消掉该信号处理器
            swoole_event_del($this->swProcess->pipe);// 删除管道上的事件循环
            EsTimer::getInstance()->clearAll();// 清除定时器
            Event::exit();// 退出事件循环
            Process::kill($this->getPid(), SIGTERM);// 再发一次SIGTERM终止当前进程
        });

        Bootstrap::boot();

        $this->logger = Container::get(LoggerInterface::class);
        $this->logger->info('启动守卫程序');

        // 只有主服才执行的逻辑
        if (self::isMaster()) {
            $this->logger->info("启动主服守卫程序");
            self::addMasterFlag();
            $this->masterDefender();
        }

        // 30 分钟一次，清理目录中的无用文件
        Timer::tick(1800000, Closure::fromCallable([$this, 'clearDir']));
    }

    private static function isMaster(): bool
    {
        // 先看环境变量
        $master = getenv('WECARSWOOLE_MASTER');
        if ($master && trim($master) == 1) {
            return true;
        }

        // 看常量
        if (defined('WECARSWOOLE_MASTER') && WECARSWOOLE_MASTER) {
            return true;
        }

        // 看ip配置（历史兼容）
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
        // 15 秒一次，执行失败重试
        Timer::tick(15000, Closure::fromCallable([TaskRetry::getInstance(), 'watch']));
        // 30 秒一次，检测队列状态
        Timer::tick(30000, Closure::fromCallable([QueueMonitor::getInstance(), 'watch']));
        // 3 小时一次，归档 task 数据
        Timer::tick(10800000, Closure::fromCallable([$this, 'fileData']));
    }

    /**
     * 清理最后修改时间是 6 小时前的目录
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
            $this->logger->error("守卫进程执行异常。msg:{$e->getMessage()},trace:" . $e->getTraceAsString());
        }
    }

    /**
     * 归档 3 个月前的数据
     */
    private function fileData()
    {
        $hour = intval(date('G'));

        // 只在晚上 0 - 6 点处理
        if ($hour > 6) {
            return;
        }

        $optimize = mt_rand(0, 100) > 95 ? true : false;

        try {
            Container::get(ITaskRepository::class)->fileTask(time() - 86400 * 30 * 3, $optimize);
        } catch (\Exception $e) {
            $this->logger->error("守卫进程执行异常。msg:{$e->getMessage()},trace:" . $e->getTraceAsString());
        }
    }
}
