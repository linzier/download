<?php

namespace App\Foundation\File;

use App\ErrCode;
use App\Exceptions\FileException;
use WecarSwoole\Exceptions\Exception;
use WecarSwoole\Util\File;

/**
 * Local file
 */
class LocalFile
{
    private $file;
    private $fileName;

    public function __construct(string $fileName, string $mode = 'w+')
    {
        $this->openFile($fileName, $mode);
        $this->fileName = $fileName;
    }

    public function __destruct()
    {
        if ($this->file && is_resource($this->file)) {
            fclose($this->file);
        }
    }

    /**
     * Save data to a CSV file
     * @param array $dataList One-dimensional or two-dimensional array
     */
    public function saveAsCsv(array $dataList)
    {
        $dataList = self::formatDataList($dataList);
        foreach ($dataList as $item) {
            if (fputcsv($this->file, $item) === false) {
                throw new FileException("Failed to write CSV file: {$this->fileName}", ErrCode::FILE_OP_FAILED);
            }
        }
    }

    /**
     * File size
     */
    public function size(): int
    {
        clearstatcache();
        return filesize($this->fileName);
    }

    public function close()
    {
        fclose($this->file);
    }

    /**
     * Delete a directory (including all files within it)
     * @return bool True on success, false on failure
     */
    public static function deleteDir(string $dir): bool
    {
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }

        // Delete all files in the directory (theoretically only one)
        foreach (scandir($dir) as $fileOrDir) {
            if ($fileOrDir == '.' || $fileOrDir == '..') {
                continue;
            }

            $file = File::join($dir, $fileOrDir);
            if (is_file($file)) {
                unlink($file);
            } else {
                self::deleteDir($fileOrDir);
            }
        }

        // Remove the empty directory
        rmdir($dir);

        return true;
    }

    private static function formatDataList(array $dataList): array
    {
        if (!$dataList) {
            return [];
        }

        if (!is_array(reset($dataList))) {
            $dataList = [$dataList];
        }

        // Validate data format
        self::validateCSVOrgData($dataList);

        return array_map(function ($item) {
            return array_values($item);
        }, $dataList);
    }

    /**
     * Validate CSV source data format.
     * Format must be: [['name' => 'John', 'age' => 18]]
     */
    private static function validateCSVOrgData(array $data)
    {
        if (!is_array(reset($data))) {
            throw new Exception("Invalid data format: must be a two-dimensional array", ErrCode::DATA_FORMAT_ERR);
        }

        // Values in the second dimension must be scalar
        $first = $data[0];
        foreach ($first as $val) {
            if (!is_null($val) && !is_scalar($val)) {
                throw new Exception("Invalid data format: values in the second dimension must be scalar types", ErrCode::DATA_FORMAT_ERR);
            }
        }
    }

    protected function openFile(string $fileName, string $mode)
    {
        $dir = dirname($fileName);
        if (!file_exists($dir)) {
            mkdir($dir, 0744, true);
        }

        $file = fopen($fileName, $mode);
        if ($file === false) {
            throw new FileException("Failed to open file: {$fileName}", ErrCode::FILE_OP_FAILED);
        }

        $this->file = $file;
    }
}
