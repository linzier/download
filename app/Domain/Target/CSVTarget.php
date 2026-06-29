<?php

namespace App\Domain\Target;

/**
 * 目标文件：CSV
 */
class CSVTarget extends Target
{
    public function __construct(string $baseDir, string $downloadFileName = '')
    {
        parent::__construct($baseDir, $downloadFileName, self::TYPE_CSV);
    }
}
