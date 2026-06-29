<?php

namespace App\Domain\Target\Template\Excel;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * 表格模板工厂
 */
trait TplBuilder
{
    /**
     * @param array|string $tplCfg 模板配置
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
            throw new Exception("模板格式不合法", ErrCode::TPL_FMT_ERR);
        }

        $rowCfg = self::formatConf($tplCfg['row'] ?? []);
        $colCfg = self::formatConf($tplCfg['col'] ?? $tplCfg);

        $colHead = self::buildColHead($colCfg);
        $rowHead = self::buildRowHead($rowCfg);

        return new Tpl($colHead, $rowHead);
    }

    /**
     * 格式化配置数组
     */
    public static function formatConf(array $conf): array
    {
        // 如果是一维数组，格式化为二维数组
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
            throw new Exception("模板格式错误：缺少列标题配置", ErrCode::TPL_FMT_ERR);
        }

        return ColHead::parse($colCfg);
    }
}
