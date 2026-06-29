<?php

namespace App\Http\Controllers\V1;

use App\Domain\Target\ExcelTarget;
use App\Domain\Target\Template\Excel\Style;
use App\Domain\Target\Template\Excel\Tpl;

trait ParamUtil
{
    /**
     * 对参数做一些处理
     * @param array $params
     * @return array
     */
    public static function dealParams(array $params): array
    {
        // 目前只需要处理 col_align
        // 将 col_align 参数植入到 template 中
        if (!isset($params['col_align']) || !$params['col_align'] || !isset($params['template']) || !$params['template']) {
            return $params;
        }

        $tplCfg = is_string($params['template']) ? json_decode($params['template'], true) : $params['template'];
        $align = $params['col_align'];

        if (isset($params['multi_type']) && $params['multi_type'] != ExcelTarget::MT_SINGLE) {
            $cnt = count($tplCfg);

            // 多表格
            if (is_string($align)) {
                if (in_array($align, [Style::ALIGN_LEFT, Style::ALIGN_CENTER, Style::ALIGN_RIGHT])) {
                    $align = array_pad([], $cnt, $align);
                } else {
                    $align = json_decode($align, true);
                }
            }

            $tpls = [];
            foreach ($tplCfg as $i => $tpl) {
                $tpls[] = self::innerDealTpl($tpl, $align[$i]);
            }
            $params['template'] = $tpls;
        } else {
            // 单表格
            $params['template'] = self::innerDealTpl($tplCfg, $align);
        }

        return $params;
    }

    private static function innerDealTpl(array $tplCfg, string $align): array
    {
        // 先格式化
        $rowCfg = Tpl::formatConf($tplCfg['row'] ?? []);
        $colCfg = Tpl::formatConf($tplCfg['col'] ?? $tplCfg);

        // 对 colCfg 应用 col_align
        self::innerDealColStyle($colCfg, $align);

        return ['row' => $rowCfg, 'col' => $colCfg];
    }

    private static function innerDealColStyle(array &$colCfg, string $colAlign)
    {
        foreach ($colCfg as &$col) {
            if (!isset($col['children']) || !$col['children']) {
                // 找到叶子节点
                $style = $col['style'] ?? [];

                if (!isset($style['align']) || !$style['align']) {
                    $style['align'] = $colAlign;
                }

                $col['style'] = $style;

                continue;
            }

            // 继续往下找
            self::innerDealColStyle($col['children'], $colAlign);
        }
    }
}
