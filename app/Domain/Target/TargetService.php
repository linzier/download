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
 * Target file service
 */
class TargetService
{
    /**
     * Generate target file
     */
    public function generate(ISource $source, Target $target, bool $compressFile = true)
    {
        // Generator
        switch ($target->type()) {
            case Target::TYPE_CSV:
                $generator = new CSVGenerator();
                break;
            case Target::TYPE_EXCEL:
                $generator = new ExcelGenerator();
                break;
            default:
                throw new TargetException("Unsupported target file type: {$target->type()}", ErrCode::FILE_TYPE_ERR);
        }

        // Compressor
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
