<?php

namespace App\Domain\Source;

use App\Domain\Target\Target;
use App\Foundation\File\LocalFile;
use App\ErrCode;
use App\Exceptions\SourceException;
use App\Foundation\Client\API;
use Swoole\Coroutine;
use WecarSwoole\Util\File;
use WecarSwoole\Util\GetterSetter;

/**
 * CSV data source: stores source data in CSV format.
 * Supports multiple sources; source data sets are separated by SPLIT_LINE.
 */
class CSVSource implements ISource
{
    use GetterSetter;
    
    public const STEP_MIN = 100;
    public const STEP_MAX = 5000;
    public const STEP_DEFAULT = 1000;
    public const SOURCE_FNAME = 'source.csv';
    public const EXT_FIELD = '_row_head_';
    public const SPLIT_LINE = '-#-=@=-#-';

    /**
     * @var array Data sources
     */
    protected $srcs;
    protected $step;
    protected $interval;
    // Whether single-source or multi-source mode
    protected $sourceType;

    // Generated local file name
    private $fileName;
    // Number of data records (rows)
    private $count;
    // Source file size
    private $size;
    private $taskId;

    /**
     * @param string|array $src Data source. Format:
     *        Single table mode:
     *           A single URL string, or a two-dimensional array of source data, e.g. "https://mp.domain.cn/...." or [["name"=>"John"],...]
     *        Multi-table mode (taking two tables as an example):
     *           Multiple URLs in an array, e.g. ["https://mp.domain.cn/pathtos1", "https://mp.domain.cn/pathtos2"]
     *           Multiple source data arrays, e.g. [[["name"=>"John"],...], [["order_code"=>"1234"],...]]
     *           Mixed URLs and source data, e.g. [[["name"=>"John"],...], "https://mp.domain.cn/...."]
     * @param string $dir Base path for local file storage
     * @param string $taskId Associated task ID. Stores taskId instead of Task primarily to avoid circular dependencies
     * @param int $step Fetch step size (number of records per page)
     * @param int $interval Time interval between two fetches, in milliseconds
     * @param int $sourceType Single-source or multi-source mode
     * @throws \Exception
     */
    public function __construct(
        $src,
        string $dir,
        string $taskId,
        int $step = self::STEP_DEFAULT,
        int $interval = self::DEFAULT_INTERVAL,
        int $sourceType = self::SOURCE_TYPE_SIMPLE
    ) {
        if (!$src) {
            throw new \Exception("source error:source are empty", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->taskId = $taskId;
        $this->interval = $interval;
        $this->sourceType = $sourceType;
        $this->srcs = $this->formatSrc($src);
        $this->setStep($step);
        $this->setFileName($dir);
    }

    /**
     * Source file name (including directory)
     */
    public function fileName(): string
    {
        return $this->fileName;
    }

    /**
     * Number of data records (rows)
     */
    public function count(): int
    {
        return $this->count;
    }

    public function srcs(): array
    {
        return $this->srcs;
    }

    public function interval(): int
    {
        return $this->interval;
    }

    /**
     * Source file size in bytes
     */
    public function size(): int
    {
        return $this->size;
    }

    public function fetch(API $invoker, string $targetType)
    {
        $cnt = 0;
        $file = new LocalFile($this->fileName());

        try {
            foreach ($this->srcs as $src) {
                if (is_string($src)) {
                    // Fetch from URL
                    $cnt += $this->fetchFromUrl($invoker, $src, $file, $targetType);
                } else {
                    // Fetch from data
                    $cnt += $this->fetchFromData($src, $file, $targetType);
                }
            }

            $this->count = $cnt;
            $this->size = $file->size();
        } catch (\Throwable $e) {
            throw new SourceException($e->getMessage(), $e->getCode());
        } finally {
            $file->close();
        }
    }

    /**
     *  Fetch data from URL in a loop and write to a local file
     * @param API $invoker
     * @param string $src
     * @param LocalFile $file
     * @param string $targetType
     * @return int Number of records
     * @throws SourceException
     * @throws \App\Exceptions\FileException
     */
    private function fetchFromUrl(API $invoker, string $src, LocalFile $file, string $targetType): int
    {
        $page = $n = $total = $cnt = 0;
        $fieldNum = 0;
        $gotNoEmptyData = false;// Whether non-empty data has been fetched (early fetches may return empty data, in which case field information is unavailable)

        $invoker->setUrl($src);

        while ($n++ < 20000) {
            $result = $this->invokeData($invoker, $page, $this->step);

            if (!isset($result['data'])) {
                break;
            }

            $data = $result['data'];
            // If force_continue is provided, the request continues unless the client sets this parameter to 0
            // This handles the case where the client filters data after fetching from the database, resulting in fewer records than page_size even though more data is available
            $forceContinue = $result['force_continue'] ?? null;

            if (!$forceContinue && !$data) {
                break;
            }

            // Save to file
            list($c, $fieldNum) = $this->innerSaveToFile($file, $data, $targetType, !$gotNoEmptyData && count($data));
            $cnt += $c;

            if ($n == 1) {
                $total = $result['total'] ?? PHP_INT_MAX;// If total is not provided, keep fetching data in a loop until exhausted
            }

            // If the API explicitly provides force_continue, check that value first
            if ($forceContinue === 0) {
                break;
            }

            // Two safeguards are applied here for robustness, to prevent infinite data fetching if the remote API has a bug
            if ($forceContinue === null && count($data) < $this->step || $cnt >= $total) {
                break;
            }

            if (count($data)) {
                $gotNoEmptyData = true;
            }

            $page++;

            if ($this->interval > 0) {
                Coroutine::sleep($this->interval / 1000);
            }
        }

        // Append separator at the end of the file
        if ($targetType == Target::TYPE_EXCEL && $fieldNum > 0) {
            // Add separator between source data sets
            $file->saveAsCsv(array_pad([], $fieldNum, self::SPLIT_LINE));
        }

        return $cnt;
    }

    /**
     * @param array $data
     * @param LocalFile $file
     * @param bool $recordColType
     * @return int
     * @throws \App\Exceptions\FileException
     */
    private function fetchFromData(array $data, LocalFile $file, string $targetType): int
    {
        if (!$data) {
            return 0;
        }

        list($cnt, $fieldNum) = $this->innerSaveToFile($file, $data, $targetType, true);

        // Append separator at the end of the file
        if ($targetType == Target::TYPE_EXCEL && $fieldNum > 0) {
            // Add separator between source data sets
            $file->saveAsCsv(array_pad([], $fieldNum, self::SPLIT_LINE));
        }

        return $cnt;
    }

    /**
     * @param LocalFile $file
     * @param array $data
     * @param string $targetType
     * @param bool $saveFields
     * @return array Format [record count, column count]
     * @throws \App\Exceptions\FileException
     */
    private function innerSaveToFile(LocalFile $file, array $data, string $targetType, bool $saveFields): array
    {
        // Format into a uniform two-dimensional array
        $data = $this->formatSourceData($data);

        if (!$data) {
            return [0, 0];
        }

        // Write field keys and column types (currently only supports number and string types)
        // Storage format: field|type, e.g. age|number,uname|string
        if ($saveFields) {
            $fields = [];
            foreach ($data[0] as $field => $value) {
                if ($targetType == Target::TYPE_EXCEL) {
                    $fields[] = $field . '|' . (is_int($value) || is_float($value) ? 'number' : 'string');
                } else {
                    $fields[] = $field;
                }
            }
            $file->saveAsCsv($fields);
        }

        // Store data
        $file->saveAsCsv($data);

        return [count($data), count(reset($data))];
    }

    /**
     * Format source data arrays into a unified two-dimensional array, incorporating the Excel row header
     * @param array $data Raw data array
     *      Supported formats (up to four dimensions):
     *          Two-dimensional array:
     *              [
     *                  ['name'=>'John', 'age'=> 18],
     *              ]
     *          Three-dimensional array (row header format):
     *              [
     *                  'row_head_one' => [
     *                      ['name'=>'John', 'age'=> 18],
     *                  ]
     *              ]
     * @return array Formatted array:
     * [
     *      ["name" => "John", "age" => 18]
     * ]
     */
    private function formatSourceData(array $data): array
    {
        if (!$data) {
            return [];
        }

        $firstEle = reset($data);

        /**
         * Two-dimensional array
         */
        if (!$firstEle || !is_array($firstEle)) {
            return [];
        }

        // Check the first element of the second dimension
        if (!is_array(reset($firstEle))) {
            return $data;
        }

        /**
         * Single-source three-dimensional array
         */
        $newData = [];
        foreach ($data as $rowHead => $item) {
            foreach ($item as $subItem) {
                $subItem[self::EXT_FIELD] = $rowHead;
                $newData[] = $subItem;
            }
        }

        return $newData;
    }

    private function invokeData(API $invoker, int $page, int $pageSize): array
    {
        $result = $invoker->invoke(['page' => $page, 'page_size' => $pageSize, '_task_id' => $this->taskId]);
         
        if (!$result || !isset($result['status']) || $result['status'] !== 200) {
            throw new SourceException(
                "Failed to fetch source data. Response: " . print_r($result, true),
                ErrCode::FETCH_SOURCE_FAILED
            );
        }

        if (!isset($result['data']['data'])) {
            throw new SourceException(
                "Failed to fetch source data. Invalid data format: " . print_r($result, true),
                ErrCode::FETCH_SOURCE_FAILED
            );
        }

        return $result['data'];
    }

    private function setFileName(string $dir)
    {
        $this->fileName = File::join($dir, self::SOURCE_FNAME);
    }

    private function setStep(int $step)
    {
        if ($step < self::STEP_MIN || $step > self::STEP_MAX) {
            $step = self::STEP_DEFAULT;
        }

        $this->step = $step;
    }

    /**
     * This format method supports both unformatted and pre-formatted source data
     * @param $src
     * @return array
     * @throws \Exception
     */
    private function formatSrc($src): array
    {
        if ($this->sourceType == self::SOURCE_TYPE_SIMPLE) {
            // Single-source mode
            // Single URL string: "https://..."
            if (self::isUrlSource($src)) {
                return [$src];
            }

            // JSON string of data: "[{"k":"v"}]"
            if (is_string($src)) {
                return [json_decode($src, true)];
            }

            // Pre-formatted URL array: ["https://...",]
            if (self::isUrlSource(reset($src))) {
                return $src;
            }

            // Pre-formatted data array: [[[k=>v],],]
            $firstEle = reset($src);
            if (is_array(reset($firstEle))) {
                return $src;
            }

            // Unformatted data array: [[k=>v],]
            return [$src];
        }

        // Multi-source mode
        if (!is_array($src)) {
            throw new \Exception("In multi-table mode, source must be in list format", ErrCode::SOURCE_FORMAT_ERR);
        }

        foreach ($src as $i => $v) {
            if (is_string($v) && !self::isUrlSource($v)) {
                $v = json_decode($v, true);
                $src[$i] = $v;
            }
        }

        return $src;
    }

    private static function isUrlSource($src): bool
    {
        return is_string($src) && strpos($src, "http") === 0;
    }
}
