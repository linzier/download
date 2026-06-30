<?php

namespace App\Domain\Target\Generator;

use App\Domain\Source\CSVSource;
use App\Foundation\File\ICompress;
use App\Domain\Target\CSVTarget;
use App\Domain\Source\ISource;
use App\ErrCode;
use App\Exceptions\FileException;
use EasySwoole\EasySwoole\Config;
use WecarSwoole\Util\File;

/**
 * CSV file generator
 */
class CSVGenerator
{
    /**
     * Generate CSV target file
     */
    public function generate(ISource $source, CSVTarget $target, ICompress $compress = null)
    {
        if (!$source instanceof CSVSource) {
            throw new \Exception("generate csv error:need CSVSource type", ErrCode::SOURCE_TYPE_ERR);
        }

        $sourceFileName = $source->fileName();
        if (!$sourceFileName || !file_exists($sourceFileName)) {
            throw new FileException("Failed to generate CSV target file: source file does not exist. source: {$sourceFileName}", ErrCode::FILE_OP_FAILED);
        }

        if (rename($sourceFileName, $target->targetFileName()) === false) {
            throw new FileException("generate target file fail.rename failed.", ErrCode::FILE_OP_FAILED);
        }

        // Compress
        if ($compress && $source->size() > Config::getInstance()->getConf("zip_threshold")) {
            $newTargetFileName = $compress->compress(File::join($target->getBaseDir(), 'target'), [$target->targetFileName()]);
            // Reset target file name
            $target->setTargetFileName($newTargetFileName);
        }
    }
}
