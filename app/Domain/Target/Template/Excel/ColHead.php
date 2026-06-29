<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * 列标头
 */
class ColHead extends Node
{
    use NodeParser;

    public const DT_STR = 'string';// 默认类型
    public const DT_NUM = 'number';
    public const DT_RICH = 'rich';// 富文本

    // 列数据类型
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
            throw new Exception("模板错误：数据格式不合法：{$dataType}", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->dataType = $dataType;
    }
}
