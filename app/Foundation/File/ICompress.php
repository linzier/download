<?php

namespace App\Foundation\File;

/**
 * File compression interface
 */
interface ICompress
{
    /**
     * @param string $archiveFileName Archived file name
     * @param array $origFileNames Array of files to archive
     * @param bool $delOrigFile Whether to delete source files after archiving
     * @return string The archived file name
     */
    public function compress(string $archiveFileName, array $origFileNames, bool $delOrigFile = true): string;
}
