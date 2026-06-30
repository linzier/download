<?php

namespace App\Domain\Target;

/**
 * Target file: CSV
 */
class CSVTarget extends Target
{
    public function __construct(string $baseDir, string $downloadFileName = '')
    {
        parent::__construct($baseDir, $downloadFileName, self::TYPE_CSV);
    }
}
