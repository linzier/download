<?php

namespace App\Domain\Target;

use App\Domain\Source\ISource;
use App\Foundation\File\Zip;
use App\Domain\Target\Generator\CSVGenerator;
use App\Domain\Target\Generator\ExcelGenerator;
use App\ErrCode;
use App\Exceptions\TargetException;
use EasySwoole\EasySwoole\Config;

/**
 * 目标文件服务
 */
class TargetService
{
    /**
     * 生成目标文件
     */
    public function generate(ISource $source, Target $target, bool $compressFile = true)
    {
        // 生成器
        switch ($target->type()) {
            case Target::TYPE_CSV:
                $generator = new CSVGenerator();
                break;
            case Target::TYPE_EXCEL:
                $generator = new ExcelGenerator();
                break;
            default:
                throw new TargetException("不支持的目标文件类型：{$target->type()}", ErrCode::FILE_TYPE_ERR);
        }

        // 压缩器
        if ($compressFile) {
            switch (Config::getInstance()->getConf('zip_type')) {
                case COMPRESS_TYPE_ZIP:
                default:
                    $compress = new Zip();
                    break;
            }
        } else {
            $compress = null;
        }
        
        $generator->generate($source, $target, $compress);
    }
}
