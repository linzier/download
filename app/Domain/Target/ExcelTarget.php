<?php

namespace App\Domain\Target;

use App\Domain\Target\Template\Excel\Tpl;

/**
 * 目标文件：Excel
 * 为了处理上的一致性，将 multi_type = single（单表格模式）情况下的元数据也转成数组格式
 */
class ExcelTarget extends Target
{
    // multi_type：page 模式，一个页面多个表格
    public const MT_PAGE = 'page';
    // multi_type：tab 模式，一个 excel 多个 tab，每个 tab 一个表格
    public const MT_TAB = 'tab';
    // multi_type：single 模式，只有一个表格（默认模式）
    public const MT_SINGLE = 'single';

    // 表格标题，一维数组
    protected $titles;
    // 摘要，一维数组
    protected $summaries;
    // 表头，二维数组
    protected $headers;
    // 表尾，二维数组
    protected $footers;
    protected $headersAlign;
    // 表尾，二维数组
    protected $footersAlign;
    // 表格模板，一维数组（元素是 Tpl）
    protected $templates;
    // 默认列宽度
    protected $defaultWidth;
    // 默认行高度
    protected $defaultHeight;
    // 多表格类型：page、tab、single
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
     * 重写 setMeta，将数组中的各部分赋值给相应的属性，让其更具有语义
     * 注意，此处是增量覆盖
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
            // 确保是二维数组
            $this->headers = is_array(reset($metaData['headers'])) ? $metaData['headers'] : [$metaData['headers']];
        }
        
        if (isset($metaData['footers']) && $metaData['footers']) {
            $metaData['footers'] = is_string($metaData['footers']) ? json_decode($metaData['footers'], true) : $metaData['footers'];
            // 确保是二维数组
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
            // 如果没有静态 template，且有提供源数据，则试图从源数据解析出模板
            $this->setTpls(Tpl::getDefaultTplFromData($metaData['data']));
        }

        $this->rowoffset = intval($metaData['rowoffset'] ?? 0);

        $this->metaData = $this->getMeta();
    }

    /**
     * 重写 getMeta
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
     * 由于 ExcelTarget 内部使用复数表示，但外面传入的可能是单数（单表格和多表格模式对外面的接口参数是一致的），此处需要做兼容处理
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
     * 表格模板
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

        // 数组里面是 Tpl 实例
        if (reset($templates) instanceof Tpl) {
            $this->templates = $templates;
            return;
        }

        $templates = $this->formatSimpleTplConf($templates);

        // 如果是单模板，则转成兼容模式
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
     * 判断是否单模板配置
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
