<?php

namespace App\Foundation\File;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;
use ZipArchive;

/**
 * ZIP archive compression
 */
class Zip implements ICompress
{
    public function compress(string $archiveFileName, array $origFileNames, bool $delOrigFile = true): string
    {
        $zip = new ZipArchive();

        $archiveFileName .= strpos($archiveFileName, '.') === false ? '.zip' : ''; 

        if (!file_exists($archiveFileName)) {
            // Create the file first, otherwise some operating systems will throw an error
            touch($archiveFileName);
            chmod($archiveFileName, 0755);
        }
        
        if ($zip->open($archiveFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("can not open zip file", ErrCode::FILE_OP_FAILED);
        }

        foreach ($origFileNames as $index => $fileName) {
            if (!is_readable($fileName)) {
                continue;
            }
            if (!$zip->addFile($fileName, strval($index + 1) . '.' . explode('.', $fileName)[1])) {
                $zip->close();
                throw new Exception("add file to zip failed", ErrCode::FILE_OP_FAILED);
            }
        }

        $zip->close();

        // Files can only be deleted after zip is closed
        if ($delOrigFile) {
            foreach ($origFileNames as $fileName) {
                unlink($fileName);
            }
        }

        return $archiveFileName;
    }
}
