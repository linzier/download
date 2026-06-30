<?php

namespace App\Domain\Target;

use App\Domain\Target\Template\Excel\Tpl;

/**
 * Target file: Excel
 * For consistency in processing, metadata is also converted to array format in multi_type = single (single table mode)
 */
class ExcelTarget extends Target
{
    // multi_type: page mode, multiple tables on one page
    public const MT_PAGE = 'page';
    // multi_type: tab mode, multiple tabs in one excel, one table per tab
    public const MT_TAB = 'tab';
    // multi_type: single mode, only one table (default mode)
    public const MT_SINGLE = 'single';

    // Table titles, 1D array
    protected $titles;
    // Summaries, 1D array
    protected $summaries;
    // Headers, 2D array
    protected $headers;
    // Footers, 2D array
    protected $footers;
    protected $headersAlign;
    // Footers alignment, 2D array
    protected $footersAlign;
    // Table templates, 1D array (elements are Tpl)
    protected $templates;
    // Default column width
    protected $defaultWidth;
    // Default row height
    protected $defaultHeight;
    // Multi-table type: page, tab, single
    protected $multiType;
    protected $rowoffset;

    public function __construct(string $baseDir, string $downloadFileName = '', string $multiType = self::MT_SINGLE)
    {
        $this->setMultiType($multiType);
        parent::__construct($baseDir, $downloadFileName, self::TYPE_EXCEL);
    }

    public function rowOffset(): int
    {
        return $this->rowoffset;
    }

    public function getTitles($index = null)
    {
        if ($index === null) {
            return $this->titles ?? [];
        } else {
            return $this->titles  && isset($this->titles[$index]) ? $this->titles[$index] : '';
        }
    }

    public function getSummaries($index = null)
    {
        if ($index === null) {
            return $this->summaries ?? [];
        } else {
            return $this->summaries  && isset($this->summaries[$index]) ? $this->summaries[$index] : '';
        }
    }

    public function getHeaders($index = null)
    {
        if ($index === null) {
            return $this->headers ?? [];
        } else {
            return $this->headers  && isset($this->headers[$index]) ? $this->headers[$index] : [];
        }
    }

    public function getFooters($index = null)
    {
        if ($index === null) {
            return $this->footers ?? [];
        } else {
            return $this->footers  && isset($this->footers[$index]) ? $this->footers[$index] : [];
        }
    }

    public function getHeadersAlign($index = null)
    {
        if ($index === null) {
            return $this->headersAlign ?? ['right'];
        } else {
            return $this->headersAlign  && isset($this->headersAlign[$index]) ? $this->headersAlign[$index] : 'right';
        }
    }

    public function getFootersAlign($index = null)
    {
        if ($index === null) {
            return $this->footersAlign ?? ['right'];
        } else {
            return $this->footersAlign  && isset($this->footersAlign[$index]) ? $this->footersAlign[$index] : 'right';
        }
    }

    public function getTpls($index = null)
    {
        if ($index === null) {
            return $this->templates ?? [];
        } else {
            return $this->templates  && isset($this->templates[$index]) ? $this->templates[$index] : null;
        }
    }

    public function getDefaultWidth(): int
    {
        return $this->defaultWidth ?: 18;
    }

    public function getDefaultHeight(): int
    {
        return $this->defaultHeight ?: 18;
    }

    public function getMultiType(): string
    {
        return $this->multiType;
    }

    /**
     * Override setMeta to assign each part of the array to the corresponding properties for better semantics
     * Note: this performs incremental overwrite
     */
    public function setMeta(array $metaData)
    {
        $metaData = $this->formateMetaData($metaData);
        $this->multiType = $this->multiType ?? $metaData['multi_type'] ?? self::MT_SINGLE;
        $this->defaultWidth = $metaData['default_width'] ?? $this->defaultWidth;
        $this->defaultHeight = $metaData['default_height'] ?? $this->defaultHeight;

        if (isset($metaData['titles']) && $metaData['titles']) {
            $this->titles = is_string($metaData['titles']) ? [$metaData['titles']] : $metaData['titles'];
        }

        if (isset($metaData['summaries']) && $metaData['summaries']) {
            $this->summaries = is_string($metaData['summaries']) ? [$metaData['summaries']] : $metaData['summaries'];
        }

        if (isset($metaData['headers']) && $metaData['headers']) {
            $metaData['headers'] = is_string($metaData['headers']) ? json_decode($metaData['headers'], true) : $metaData['headers'];
            // Ensure it is a 2D array
            $this->headers = is_array(reset($metaData['headers'])) ? $metaData['headers'] : [$metaData['headers']];
        }
        
        if (isset($metaData['footers']) && $metaData['footers']) {
            $metaData['footers'] = is_string($metaData['footers']) ? json_decode($metaData['footers'], true) : $metaData['footers'];
            // Ensure it is a 2D array
            $this->footers = is_array(reset($metaData['footers'])) ? $metaData['footers'] : [$metaData['footers']];
        }

        if (isset($metaData['headers_align']) && $metaData['headers_align']) {
            $metaData['headers_align'] = is_string($metaData['headers_align']) ? [$metaData['headers_align']] : $metaData['headers_align'];
            $this->headersAlign = $metaData['headers_align'];
        }

        if (isset($metaData['footers_align']) && $metaData['footers_align']) {
            $metaData['footers_align'] = is_string($metaData['footers_align']) ? [$metaData['footers_align']] : $metaData['footers_align'];
            $this->footersAlign = $metaData['footers_align'];
        }

        if (isset($metaData['templates']) && $metaData['templates']) {
            $this->setTpls($metaData['templates']);
        } elseif (!$this->templates && isset($metaData['data']) && $metaData['data']) {
            // If no static template is provided but source data is available, attempt to parse a template from the source data
            $this->setTpls(Tpl::getDefaultTplFromData($metaData['data']));
        }

        $this->rowoffset = intval($metaData['rowoffset'] ?? 0);

        $this->metaData = $this->getMeta();
    }

    /**
     * Override getMeta
     */
    public function getMeta(string $key = '')
    {
        $data = [
            'titles' => $this->titles,
            'summaries' => $this->summaries,
            'headers' => $this->headers,
            'footers' => $this->footers,
            'headers_align' => $this->headersAlign,
            'footers_align' => $this->footersAlign,
            'templates' => $this->templates,
            'multi_type' => $this->multiType,
            'default_width' => $this->getDefaultWidth(),
            'default_height' => $this->getDefaultHeight(),
            'rowoffset' => $this->rowoffset,
        ];

        return $key ? ($data[$key] ?? null) : $data;
    }

    /**
     * ExcelTarget internally uses plural forms, but the caller may pass singular values
     * (the interface parameters are consistent for both single and multi-table modes).
     * This method handles the compatibility.
     */
    private function formateMetaData(array $meta): array
    {
        $meta['titles'] = $meta['titles'] ?? $meta['title'] ?? [];
        $meta['summaries'] = $meta['summaries'] ?? $meta['summary'] ?? [];
        $meta['headers'] = $meta['headers'] ?? $meta['header'] ?? [];
        $meta['footers'] = $meta['footers'] ?? $meta['footer'] ?? [];
        $meta['headers_align'] = $meta['headers_align'] ?? $meta['header_align'] ?? [];
        $meta['footers_align'] = $meta['footers_align'] ?? $meta['footers_align'] ?? [];
        $meta['templates'] = $meta['templates'] ?? $meta['template'] ?? [];

        return $meta;
    }

    /**
     * Table templates
     */
    private function setTpls($templates)
    {
        if (!$templates) {
            $this->templates = [];
            return;
        }
        
        if ($templates instanceof Tpl) {
            $this->templates = [$templates];
            return;
        }

        if (is_string($templates)) {
            $templates = json_decode($templates, true);
        }

        // Array contains Tpl instances
        if (reset($templates) instanceof Tpl) {
            $this->templates = $templates;
            return;
        }

        $templates = $this->formatSimpleTplConf($templates);

        // If it is a single template, convert to compatible mode
        if ($this->isSingleTplCfg($templates)) {
            $templates = [$templates];
        }

        $this->templates = [];
        foreach ($templates as $tpl) {
            $this->templates[] = Tpl::build($tpl);
        }
    }

    private function formatSimpleTplConf(array $conf): array
    {
        if (is_string(reset($conf))) {
            $newConf = [];
            foreach ($conf as $key => $val) {
                $newConf[] = ['name' => $key, 'title' => $val];
            }
            return $newConf;
        }

        return $conf;
    }

    /**
     * Check if the configuration is a single template
     */
    private function isSingleTplCfg(array $cfg): bool
    {
        if (isset($cfg['col']) || isset($cfg['row'])) {
            return true;
        }

        $firstEle = reset($cfg);
        if (isset($firstEle['title']) || isset($firstEle['name']) || isset($firstEle['children'])) {
            return true;
        }

        return false;
    }

    private function setMultiType(string $multiType)
    {
        if (!in_array($multiType, [self::MT_PAGE, self::MT_SINGLE, self::MT_TAB])) {
            $multiType = self::MT_SINGLE;
        }
        $this->multiType = $multiType;
    }
}
