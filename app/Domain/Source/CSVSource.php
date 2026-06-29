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
 * CSV 数据源，将源数据存储为 CSV 格式
 * 支持多源，多个源数据之间通过 SPLIT_LINE 分割
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
     * @var array 数据源
     */
    protected $srcs;
    protected $step;
    protected $interval;
    // 单源还是多源模式
    protected $sourceType;

    // 生成的本地文件名
    private $fileName;
    // 数据记录数（行数）
    private $count;
    // 源文件大小
    private $size;
    private $taskId;

    /**
     * @param string|array $src 数据源。格式：
     *        单表格模式：
     *           单个 url 字符串，或者二维数组形式的源数据，如 "https://mp.domain.cn/...."、[["name"=>"张三"],...]
     *        多表格模式（以两表格为例）：
     *           多个 url 数组，如： ["https://mp.domain.cn/pathtos1", "https://mp.domain.cn/pathtos2"]
     *           多个源数据数组，如：[[["name"=>"张三"],...], [["order_code"=>"1234"],...]]
     *           url 和源数据混合，如：[[["name"=>"张三"],...], "https://mp.domain.cn/...."]
     * @param string $dir 本地文件存储基路径
     * @param string $taskId 关联的任务编号。此处存储 taskId 而不是 Task 主要避免循环依赖
     * @param int $step 取数步长（每页取多少）
     * @param int $interval 两次拉取之间时间间隔，单位毫秒
     * @param int $sourceType 单源还是多源模式
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
     * 源文件名称（包含目录）
     */
    public function fileName(): string
    {
        return $this->fileName;
    }

    /**
     * 数据记录数（行数）
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
     * 源文件大小，单位字节
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
                    // url 拉取
                    $cnt += $this->fetchFromUrl($invoker, $src, $file, $targetType);
                } else {
                    // data 数据
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
     *  从 url 循环拉取并写入到本地文件
     * @param API $invoker
     * @param string $src
     * @param LocalFile $file
     * @param string $targetType
     * @return int 记录数
     * @throws SourceException
     * @throws \App\Exceptions\FileException
     */
    private function fetchFromUrl(API $invoker, string $src, LocalFile $file, string $targetType): int
    {
        $page = $n = $total = $cnt = 0;
        $fieldNum = 0;
        $gotNoEmptyData = false;// 是否已经获取到了非空数据（有可能前几次拿到的数据都是空，此时我们无法拿到 field 信息）

        $invoker->setUrl($src);

        while ($n++ < 20000) {
            $result = $this->invokeData($invoker, $page, $this->step);

            if (!isset($result['data'])) {
                break;
            }

            $data = $result['data'];
            // 如果传了force_continue，则除非客户端将该参数设置为 0，否则会继续请求
            // 应对客户端从数据库取出数据后又做了过滤的情况，这种情况下有可能获取到的数据条数小于 page_size，但实际上后面还有数据
            $forceContinue = $result['force_continue'] ?? null;

            if (!$forceContinue && !$data) {
                break;
            }

            // 保存到文件
            list($c, $fieldNum) = $this->innerSaveToFile($file, $data, $targetType, !$gotNoEmptyData && count($data));
            $cnt += $c;

            if ($n == 1) {
                $total = $result['total'] ?? PHP_INT_MAX;// 如果没有提供 total，则会不停地循环拉数据直到拉完
            }

            // 如果接口方明确提供了 force_continue，则先判断该值
            if ($forceContinue === 0) {
                break;
            }

            // 为了健壮性，此处做了两方面的检测，防止对方接口有 bug 导致一直拉取数据
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

        // 文件末尾增加分隔符
        if ($targetType == Target::TYPE_EXCEL && $fieldNum > 0) {
            // 源数据之间增加分隔符
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

        // 文件末尾增加分隔符
        if ($targetType == Target::TYPE_EXCEL && $fieldNum > 0) {
            // 源数据之间增加分隔符
            $file->saveAsCsv(array_pad([], $fieldNum, self::SPLIT_LINE));
        }

        return $cnt;
    }

    /**
     * @param LocalFile $file
     * @param array $data
     * @param string $targetType
     * @param bool $saveFields
     * @return array 格式 [记录数, 列数]
     * @throws \App\Exceptions\FileException
     */
    private function innerSaveToFile(LocalFile $file, array $data, string $targetType, bool $saveFields): array
    {
        // 格式化成统一的二维数组形式
        $data = $this->formatSourceData($data);

        if (!$data) {
            return [0, 0];
        }

        // 将 key 写入，同时写入每列的类型（目前仅支持 number、string 两种类型）
        // 存入格式：field|type，如 age|number,uname|string
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

        // 存储数据
        $file->saveAsCsv($data);

        return [count($data), count(reset($data))];
    }

    /**
     * 格式化源数据数组格式，统一整理成如下数组，并将 excel 行表头纳入其中
     * @param array $data 原始数据数组
     *      原始数组有以下几种格式（最多四维）：
     *          二维数组：
     *              [
     *                  ['name'=>'张三', 'age'=> 18],
     *              ]
     *          三维数组（行列表头格式）：
     *              [
     *                  'row_head_one' => [
     *                      ['name'=>'张三', 'age'=> 18],
     *                  ]
     *              ]
     * @return array 格式化后的数组：
     * [
     *      ["name" => "张三", "age" => 18]
     * ]
     */
    private function formatSourceData(array $data): array
    {
        if (!$data) {
            return [];
        }

        $firstEle = reset($data);

        /**
         * 二维数组
         */
        if (!$firstEle || !is_array($firstEle)) {
            return [];
        }

        // 判断第二维数组的第一个元素
        if (!is_array(reset($firstEle))) {
            return $data;
        }

        /**
         * 单源三维数组
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
                "获取源数据失败：返回：" . print_r($result, true),
                ErrCode::FETCH_SOURCE_FAILED
            );
        }

        if (!isset($result['data']['data'])) {
            throw new SourceException(
                "获取源数据失败：数据格式错误：" . print_r($result, true),
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
     * 该 format 需要支持未格式化的和已经格式化的 src
     * @param $src
     * @return array
     * @throws \Exception
     */
    private function formatSrc($src): array
    {
        if ($this->sourceType == self::SOURCE_TYPE_SIMPLE) {
            // 单源模式
            // 单个 url 字符串: "https://..."
            if (self::isUrlSource($src)) {
                return [$src];
            }

            // data 的 json string: "[{"k":"v"}]"
            if (is_string($src)) {
                return [json_decode($src, true)];
            }

            // 已经格式化好了的 url 数组: ["https://...",]
            if (self::isUrlSource(reset($src))) {
                return $src;
            }

            // 已经格式化好了的 data 数组: [[[k=>v],],]
            $firstEle = reset($src);
            if (is_array(reset($firstEle))) {
                return $src;
            }

            // 未格式化的 data 数组: [[k=>v],]
            return [$src];
        }

        // 多源模式
        if (!is_array($src)) {
            throw new \Exception("多表格模式下 source 必须是列表格式", ErrCode::SOURCE_FORMAT_ERR);
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
