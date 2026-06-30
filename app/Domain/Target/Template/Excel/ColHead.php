<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * Column header
 */
class ColHead extends Node
{
    use NodeParser;

    public const DT_STR = 'string';// Default type
    public const DT_NUM = 'number';
    public const DT_RICH = 'rich';// Rich text

    // Column data type
    protected $dataType;

    public function __construct(string $name = '', string $title = '', Style $style = null, string $dataType = self::DT_STR)
    {
        $this->resolveDataType($dataType);
        parent::__construct($name, $title, $style);
    }

    protected static function createNode(array $colCfg): Node
    {
        $styleCfg = $colCfg['style'] ?? [
            'bg_color' => $colCfg['bg_color'] ?? '',
            'width' => $colCfg['width'] ?? 0,
            'height' => $colCfg['height'] ?? 0,
            'align' => $colCfg['align'] ?? Style::ALIGN_LEFT,
            'color' => $colCfg['color'] ?? '',
            'bold' => $colCfg['bold'] ?? false,
        ];
        $style = new Style($styleCfg);

        return new ColHead($colCfg['name'] ?? '', $colCfg['title'] ?? '', $style, $colCfg['type'] ?? ColHead::DT_STR);
    }

    private function resolveDataType(string $dataType)
    {
        if (!$dataType) {
            $this->dataType = self::DT_STR;
            return;
        }

        $dataType = strtolower($dataType);

        if (!in_array($dataType, [self::DT_STR, self::DT_NUM, self::DT_RICH])) {
            throw new Exception("Template error: invalid data format: {$dataType}", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->dataType = $dataType;
    }
}
