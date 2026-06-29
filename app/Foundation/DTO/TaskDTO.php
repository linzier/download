<?php

namespace App\Foundation\DTO;

use App\Domain\Target\ExcelTarget;
use WecarSwoole\DTO;

/**
 * 数据传输对象，给外部传参用
 */
class TaskDTO extends DTO
{
    public $id;
    public $name;
    public $sourceUrl;
    public $sourceData;
    public $source;
    public $projectId;
    public $fileName;
    public $type;
    public $multiType;// 多表格类型：page、tab、single（单表格模式，默认）
    public $callback;
    public $step;
    public $operatorId;
    public $merchantId;
    public $merchantType;
    public $template;
    public $title;
    public $summary;
    public $header;
    public $footer;
    public $headerAlign;
    public $footerAlign;
    public $isSync;
    public $defaultWidth;// Excel 默认列宽度，单位 pt
    public $defaultHeight;// Excel 默认行高，单位 pt
    public $maxExecTime;// 任务处理时限（超过该时限还在“处理中”的任务将重新入列）
    public $interval;// 两次拉取之间间隔多少毫秒，取值 100 ~ 3000（0.1秒到3秒）
    public $rowoffset;

    public function __construct(array $data = [])
    {
        parent::__construct($data);

        if (!$this->multiType) {
            $this->multiType = ExcelTarget::MT_SINGLE;
        }

        if (is_string($this->template)) {
            $this->template = json_decode($this->template, true);
        }

        // 将历史参数 source_url、source_data 都合并到 source 里面去
        if (!$this->source) {
            if ($this->sourceData) {
                // 优先看 sourceData
                $data = is_string($this->sourceData) ? json_decode($this->sourceData, true) : $this->sourceData;
                $this->source = $data;
            } else {
                $this->source = $this->sourceUrl;
            }
        }

        // 多表格模式下 source 必须是数组
        if ($this->multiType != ExcelTarget::MT_SINGLE && is_string($this->source)) {
            $this->source = json_decode($this->source, true);
        }
    }
}
