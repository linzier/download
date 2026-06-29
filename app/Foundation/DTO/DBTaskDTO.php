<?php

namespace App\Foundation\DTO;

/**
 * 给仓储用
 */
class DBTaskDTO extends TaskDTO
{
    public $ctime;// 创建时间
    public $etime;// 最后执行时间
    public $ftime;// 执行完成时间
    public $stime;// 最后状态变更时间
    public $qtime;// 最后入列时间
    public $status;// 状态
    public $retryNum;// 重试次数
}
