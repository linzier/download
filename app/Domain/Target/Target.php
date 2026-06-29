<?php

namespace App\Domain\Target;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;
use WecarSwoole\Util\File;

/**
 * 目标文件
 * 目标文件由 meta（元数据） + data（数据） 构成，meta 决定目标文件的内容如何展现，data 则是要展现什么
 * 不同的目标文件的 meta 信息大不相同，因而此处使用数组存储，具体的目标文件自身定义数组格式，外界根据其约定格式传递参数
 */
class Target
{
    public const TYPE_CSV = 'csv';
    public const TYPE_EXCEL = 'excel';

    protected const FILE_EXT = [
        self::TYPE_CSV => ['csv'],
        self::TYPE_EXCEL => ['xlsx', 'xls'],
    ];

    // 目标文件名称（给程序用的）
    protected $targetFileName;
    // 下载文件名称（给用户看的）
    protected $downloadFileName;
    // 目标文件类型
    protected $type;
    // 元数据
    protected $metaData = [];
    protected $baseDir;

    /**
     * @param string $baseDir 目标临时文件存放目录
     * @param string $downloadFileName 下载文件名称
     * @param string $type 文件类型
     */
    public function __construct(string $baseDir, string $downloadFileName = '', string $type = 'csv')
    {
        $this->baseDir = $baseDir;
        $this->setType($type);
        $this->setDownLoadFileName($downloadFileName);
        $this->setTargetFileName();
    }

    /**
     * 下载文件名称（给用户看的）
     */
    public function downloadFileName(): string
    {
        return $this->downloadFileName;
    }

    /**
     * 目标文件名称（给系统用的）
     */
    public function targetFileName(): string
    {
        return $this->targetFileName;
    }

    /**
     * 设置目标文件名称
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
     * 设置目标文件的元数据
     */
    public function setMeta(array $metaData)
    {
        $this->metaData = $metaData;
    }

    /**
     * 目标文件基路径
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * 获取 meta 信息
     * @return mixed
     */
    public function getMeta(string $key = '')
    {
        return $key ? ($this->metaData[$key] ?? null) : $this->metaData;
    }
    
    private function setType(string $type)
    {
        if (!in_array($type, [self::TYPE_CSV, self::TYPE_EXCEL])) {
            throw new Exception("目标文件类型不合法", ErrCode::PARAM_VALIDATE_FAIL);
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
     * 对传入的文件名称做处理，去掉前后的 .，将 / 替换成 _
     */
    private static function fixDownloadFileName(string $name): string
    {
        return trim(str_replace('/', '_', $name), '.');
    }
}
