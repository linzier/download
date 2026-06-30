<?php

namespace App\Http\Controllers\V1;

use App\Domain\Target\ExcelTarget;
use App\Domain\Target\Template\Excel\Style;
use App\Domain\Target\Template\Excel\Tpl;

trait ParamUtil
{
    /**
     * Process parameters
     * @param array $params
     * @return array
     */
    public static function dealParams(array $params): array
    {
        // Currently only need to handle col_align
        // Inject col_align parameter into the template
        if (!isset($params['col_align']) || !$params['col_align'] || !isset($params['template']) || !$params['template']) {
            return $params;
        }

        $tplCfg = is_string($params['template']) ? json_decode($params['template'], true) : $params['template'];
        $align = $params['col_align'];

        if (isset($params['multi_type']) && $params['multi_type'] != ExcelTarget::MT_SINGLE) {
            $cnt = count($tplCfg);

            // Multiple tables
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
            // Single table
            $params['template'] = self::innerDealTpl($tplCfg, $align);
        }

        return $params;
    }

    private static function innerDealTpl(array $tplCfg, string $align): array
    {
        // Format first
        $rowCfg = Tpl::formatConf($tplCfg['row'] ?? []);
        $colCfg = Tpl::formatConf($tplCfg['col'] ?? $tplCfg);

        // Apply col_align to colCfg
        self::innerDealColStyle($colCfg, $align);

        return ['row' => $rowCfg, 'col' => $colCfg];
    }

    private static function innerDealColStyle(array &$colCfg, string $colAlign)
    {
        foreach ($colCfg as &$col) {
            if (!isset($col['children']) || !$col['children']) {
                // Found leaf node
                $style = $col['style'] ?? [];

                if (!isset($style['align']) || !$style['align']) {
                    $style['align'] = $colAlign;
                }

                $col['style'] = $style;

                continue;
            }

            // Continue searching deeper
            self::innerDealColStyle($col['children'], $colAlign);
        }
    }
}
