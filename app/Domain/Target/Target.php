<?php

namespace App\Domain\Target;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;
use WecarSwoole\Util\File;

/**
 * Target file
 * A target file consists of meta (metadata) + data. Meta determines how the content is presented, while data determines what is presented.
 * Different target files have very different meta information, so we use an array to store it.
 * Each target file defines its own array format, and callers pass parameters according to the agreed format.
 */
class Target
{
    public const TYPE_CSV = 'csv';
    public const TYPE_EXCEL = 'excel';

    protected const FILE_EXT = [
        self::TYPE_CSV => ['csv'],
        self::TYPE_EXCEL => ['xlsx', 'xls'],
    ];

    // Target file name (used by the system)
    protected $targetFileName;
    // Download file name (shown to the user)
    protected $downloadFileName;
    // Target file type
    protected $type;
    // Metadata
    protected $metaData = [];
    protected $baseDir;

    /**
     * @param string $baseDir Directory for temporary target files
     * @param string $downloadFileName Download file name
     * @param string $type File type
     */
    public function __construct(string $baseDir, string $downloadFileName = '', string $type = 'csv')
    {
        $this->baseDir = $baseDir;
        $this->setType($type);
        $this->setDownLoadFileName($downloadFileName);
        $this->setTargetFileName();
    }

    /**
     * Download file name (shown to the user)
     */
    public function downloadFileName(): string
    {
        return $this->downloadFileName;
    }

    /**
     * Target file name (used by the system)
     */
    public function targetFileName(): string
    {
        return $this->targetFileName;
    }

    /**
     * Set target file name
     */
    public function setTargetFileName(string $fileName = '')
    {
        $this->targetFileName = $fileName ?: File::join($this->baseDir, $this->appendFileExt('target'));
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * Set target file metadata
     */
    public function setMeta(array $metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * Target file base path
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * Get meta information
     * @return mixed
     */
    public function getMeta(string $key = '')
    {
        return $key ? ($this->metaData[$key] ?? null) : $this->metaData;
    }
    
    private function setType(string $type)
    {
        if (!in_array($type, [self::TYPE_CSV, self::TYPE_EXCEL])) {
            throw new Exception("Invalid target file type", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->type = $type;
    }

    private function setDownLoadFileName(string $name)
    {
        if (!$name) {
            $name = self::generateDownloadFileName();
        } else {
            $name = self::fixDownloadFileName($name);
        }

        $this->downloadFileName = $this->appendFileExt($name);
    }

    private static function generateDownloadFileName(): string
    {
        return date('YmdHis');
    }

    private function appendFileExt(string $fileName): string
    {
        if ($dotPos = strrpos($fileName, '.')) {
            $ext = substr($fileName, $dotPos + 1);
        }
     
        if (isset($ext) && in_array($ext, self::FILE_EXT[$this->type])) {
            return $fileName;
        }

        return $fileName . "." . self::FILE_EXT[$this->type][0];
    }

    /**
     * Sanitize the given file name: trim leading/trailing dots, replace slashes with underscores
     */
    private static function fixDownloadFileName(string $name): string
    {
        return trim(str_replace('/', '_', $name), '.');
    }
}
