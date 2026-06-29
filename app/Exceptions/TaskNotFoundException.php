<?php

namespace App\Exceptions;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

class TaskNotFoundException extends Exception
{
    public function __construct(string $message = "")
    {
        parent::__construct($message, ErrCode::TASK_NOT_EXISTS);
    }
}
