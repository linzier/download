<?php

namespace App\Domain\Target\Template\Excel;

use App\Domain\Source\CSVSource;

/**
 * Excel 表格模板
 */
class Tpl
{
    use TplBuilder;
    
    /**
     * @var ColHead 列标头
     */
    private $col;
    /**
     * @var RowHead 行标头
     */
    private $row;

    public function __construct(ColHead $colHead, RowHead $rowHead = null)
    {
        $this->col = $colHead;
        $this->row = $rowHead;
    }

    public function colHead(): ColHead
    {
        return $this->col;
    }

    public function rowHead(): ?RowHead
    {
        return $this->row;
    }

    /**
     * 从数据中解析默认模板
     * $data 可以是一维、二维或者三维数组
     * 一维、二维数组会解析出一维模板，三维数组（多表模式）会解析出二维模板（多表模板）
     * $data:
     * 一维数组：
     * ["name" => "张三", "age" => 89]
     * 二维数组：
     * [["name" => "张三", "age" => 89]]
     * 三维数组：
     * [[["name" => "张三", "age" => 89]],[["name" => "张三", "age" => 89]]]
     */
    public static function getDefaultTplFromData(array $data): array
    {
        if (!$data) {
            return [];
        }

        $firstEle = reset($data);
        
        // 一维数组
        if (!is_array($firstEle)) {
            return self::extractTplFromData($data);
        }

        // 二维数组
        if (!is_array(reset($firstEle))) {
            return self::extractTplFromData($data[0]);
        }

        // 三维数组
        $cfg = [];
        foreach ($data as $val) {
            $cfg[] = self::extractTplFromData($val[0]);
        }

        return $cfg;
    }

    private static function extractTplFromData(array $data): array
    {
        $cfg = [];

        foreach ($data as $key => $val) {
            if ($key == CSVSource::EXT_FIELD) {
                continue;
            }
            
            $cfg[] = [
                'name' => $key,
                'title' => $key,
                'type' => is_int($val) || is_float($val) ? ColHead::DT_NUM : ColHead::DT_STR,
            ];
        }

        return $cfg;
    }
}
