<?php

namespace App\Foundation\DTO;

/**
 * Used by the repository layer
 */
class DBTaskDTO extends TaskDTO
{
    public $ctime;// Creation time
    public $etime;// Last execution time
    public $ftime;// Execution completion time
    public $stime;// Last status change time
    public $qtime;// Last enqueue time
    public $status;// Status
    public $retryNum;// Retry count
}
