<?php

namespace App\Domain\Target\Template\Excel;

use App\Domain\Source\CSVSource;

/**
 * Excel table template
 */
class Tpl
{
    use TplBuilder;
    
    /**
     * @var ColHead Column header
     */
    private $col;
    /**
     * @var RowHead Row header
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
     * Parse a default template from data
     * $data can be a 1D, 2D, or 3D array
     * 1D and 2D arrays produce a 1D template; 3D arrays (multi-table mode) produce a 2D template (multi-table template)
     * $data:
     * 1D array:
     * ["name" => "Zhang San", "age" => 89]
     * 2D array:
     * [["name" => "Zhang San", "age" => 89]]
     * 3D array:
     * [[["name" => "Zhang San", "age" => 89]],[["name" => "Zhang San", "age" => 89]]]
     */
    public static function getDefaultTplFromData(array $data): array
    {
        if (!$data) {
            return [];
        }

        $firstEle = reset($data);
        
        // 1D array
        if (!is_array($firstEle)) {
            return self::extractTplFromData($data);
        }

        // 2D array
        if (!is_array(reset($firstEle))) {
            return self::extractTplFromData($data[0]);
        }

        // 3D array
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
