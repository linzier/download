<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;
use WecarSwoole\OTA\IExtractable;
use WecarSwoole\OTA\ObjectToArray;

/**
 * 单元格样式
 */
class Style implements IExtractable
{
    use ObjectToArray;
    
    public const ALIGN_LEFT = 'left';
    public const ALIGN_CENTER = 'center';
    public const ALIGN_RIGHT = 'right';
    private const COLOR_LITERAL = ['black', 'white', 'red', 'green', 'blue', 'yellow', 'cyan'];

    // 单元格宽度，默认 0
    private $width;
    // 单元格高度，默认 0
    private $height;
    private $color;
    private $bgColor;
    private $align;
    private $bold;

    /**
     * styleCfg 可配置：width、height、color、bg_color、align、bold
     */
    public function __construct(array $styleCfg = [])
    {
        $this->resolveStyle($styleCfg);
    }

    public function setWidth(int $width)
    {
        if ($width > 4000) {
            throw new Exception("单元格宽度不合法:{$width}", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->width = $width;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setHeight(int $height)
    {
        if ($height < 0 || $height > 4000) {
            throw new Exception("单元格高度不合法:{$height}", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->height = $height;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setColor(string $color)
    {
        $this->color = self::resolveColor($color);
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setBgColor(string $color)
    {
        $this->bgColor = self::resolveColor($color);
    }

    public function getBgColor(): string
    {
        return $this->bgColor;
    }

    public function setAlign(string $align)
    {
        if (!in_array($align, [self::ALIGN_LEFT, self::ALIGN_RIGHT, self::ALIGN_CENTER])) {
            throw new Exception("非法的对齐方式：{$align}", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->align = $align;
    }

    public function getAlign(): string
    {
        return $this->align;
    }

    public function setBold($bold)
    {
        $this->bold = boolval($bold);
    }

    public function getBold(): bool
    {
        return $this->bold;
    }

    private function resolveStyle(array $styleCfg)
    {
        $this->setWidth(intval($styleCfg['width'] ?? 0));
        $this->setHeight(intval($styleCfg['height'] ?? 0));
        $this->setColor($styleCfg['color'] ?? '');
        $this->setBgColor($styleCfg['bg_color'] ?? '');
        $this->setAlign($styleCfg['align'] ?? self::ALIGN_LEFT);
        $this->setBold($styleCfg['bold'] ?? false);
    }

    private static function resolveColor(string $color): string
    {
        if (!$color) {
            return '';
        }

        if (in_array($color, self::COLOR_LITERAL)) {
            return $color;
        }

        $color = ltrim($color, '#');
        
        if (strlen($color) > 6) {
            throw new Exception("非法的颜色格式：{$color}", ErrCode::PARAM_VALIDATE_FAIL);
        }

        return $color;
    }
}
