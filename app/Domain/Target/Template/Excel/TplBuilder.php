<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * Table template builder
 */
trait TplBuilder
{
    /**
     * @param array|string $tplCfg Template configuration
     */
    public static function build($tplCfg): ?Tpl
    {
        if ($tplCfg instanceof Tpl) {
            return $tplCfg;
        }

        if ($tplCfg && is_string($tplCfg)) {
            $tplCfg = json_decode($tplCfg, true);
        }

        if (!$tplCfg) {
            return null;
        }

        if (!isset($tplCfg['col']) && isset($tplCfg['row'])) {
            throw new Exception("Invalid template format", ErrCode::TPL_FMT_ERR);
        }

        $rowCfg = self::formatConf($tplCfg['row'] ?? []);
        $colCfg = self::formatConf($tplCfg['col'] ?? $tplCfg);

        $colHead = self::buildColHead($colCfg);
        $rowHead = self::buildRowHead($rowCfg);

        return new Tpl($colHead, $rowHead);
    }

    /**
     * Format configuration array
     */
    public static function formatConf(array $conf): array
    {
        // If it is a 1D array, convert to 2D array
        if (!is_array(reset($conf))) {
            $newConf = [];
            foreach ($conf as $key => $val) {
                $newConf[] = ['name' => $key, 'title' => $val];
            }
            return $newConf;
        }

        return $conf;
    }

    private static function buildRowHead(array $rowCfg): ?RowHead
    {
        if (!$rowCfg) {
            return null;
        }

        return RowHead::parse($rowCfg);
    }

    private static function buildColHead(array $colCfg): ColHead
    {
        if (!$colCfg) {
            throw new Exception("Template format error: missing column header configuration", ErrCode::TPL_FMT_ERR);
        }

        return ColHead::parse($colCfg);
    }
}
