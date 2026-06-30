<?php

namespace App\Foundation\DTO;

use App\Domain\Target\ExcelTarget;
use WecarSwoole\DTO;

/**
 * Data Transfer Object, used for passing parameters externally
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
    public $multiType;// Multi-sheet type: page, tab, single (single-sheet mode, default)
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
    public $defaultWidth;// Default Excel column width, in pt
    public $defaultHeight;// Default Excel row height, in pt
    public $maxExecTime;// Task processing time limit (tasks still "in progress" beyond this limit will be re-enqueued)
    public $interval;// Interval in milliseconds between two fetches, valid range: 100 ~ 3000 (0.1s to 3s)
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

        // Merge legacy parameters source_url and source_data into source
        if (!$this->source) {
            if ($this->sourceData) {
                // Prioritize sourceData
                $data = is_string($this->sourceData) ? json_decode($this->sourceData, true) : $this->sourceData;
                $this->source = $data;
            } else {
                $this->source = $this->sourceUrl;
            }
        }

        // In multi-sheet mode, source must be an array
        if ($this->multiType != ExcelTarget::MT_SINGLE && is_string($this->source)) {
            $this->source = json_decode($this->source, true);
        }
    }
}
