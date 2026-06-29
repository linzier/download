<?php

namespace App\Domain\Task;

use App\Domain\Target\CSVTarget;
use App\Domain\Target\ExcelTarget;
use App\Domain\Target\Target;
use App\Domain\Project\IProjectRepository;
use App\Domain\Source\CSVSource;
use App\Domain\Source\ISource;
use App\Foundation\DTO\TaskDTO;
use EasySwoole\EasySwoole\Config;
use WecarSwoole\Container;
use WecarSwoole\Exceptions\Exception;
use WecarSwoole\ID\IIDGenerator;
use WecarSwoole\Util\File;
use App\Domain\URI;
use App\ErrCode;
use App\Foundation\DTO\DBTaskDTO;

/**
 * 工厂：创建 task 对象
 */
class TaskFactory
{
    /**
     * @param TaskDTO $taskDTO
     * @return Task
     * @throws Exception
     * @throws \Throwable
     */
    public static function create(TaskDTO $taskDTO): Task
    {
        if (!$project = Container::get(IProjectRepository::class)->getProjectById($taskDTO->projectId)) {
            throw new Exception("创建任务失败：项目不存在", ErrCode::PROJ_NOT_EXISTS);
        }

        // 此处并没有对外界传入的 id 做唯一性校验，有调用方保证
        $id = $taskDTO->id ?? Container::get(IIDGenerator::class)->id();
        $taskDTO->id = $id;

        // multiType
        $taskDTO->multiType = $taskDTO->multiType ?? ExcelTarget::MT_SINGLE;

        if ($taskDTO->multiType != ExcelTarget::MT_SINGLE) {
            // 验证多表格数据格式
            self::formatAndValidateMultiTableData($taskDTO);
        }
        
        // 源
        $source = self::buildSource($taskDTO, $taskDTO->type ?? 'csv');
        // 目标
        $target = self::buildTarget($taskDTO);
        // 回调
        $callback = new URI($taskDTO->callback ?: '');

        // 基于 DTO 创建 Task 对象
        $task = new Task(
            $id,
            $taskDTO->name,
            $project,
            $source,
            $target,
            $callback,
            $taskDTO->operatorId ?: '',
            $taskDTO->maxExecTime ?: 0,
            $taskDTO->isSync ?: 0,
            new Merchant($taskDTO->merchantId ? intval($taskDTO->merchantId) : 0, $taskDTO->merchantType ? intval($taskDTO->merchantType) : 0)
        );

        if ($taskDTO instanceof DBTaskDTO) {
            // 来自存储层的数据，需要设置其他属性
            $task->createTime = $taskDTO->ctime;
            $task->lastExecTime = $taskDTO->etime;
            $task->finishedTime = $taskDTO->ftime;
            $task->lastEnqueueTime = $taskDTO->qtime;
            $task->lastChangeStatusTime = $taskDTO->stime;
            $task->status = $taskDTO->status;
            $task->retryNum = $taskDTO->retryNum;
        }

        return $task;
    }

    /**
     * template、title、summary、header、footer、source 数组的第一维元素个数必须相同而且顺序对应一致
     * @param TaskDTO $taskDTO
     * @throws \Exception
     */
    private static function formatAndValidateMultiTableData(TaskDTO $taskDTO)
    {
        self::formatMT($taskDTO);

        if (!$taskDTO->source) {
            throw new \Exception("缺少数据源", ErrCode::TPL_FMT_ERR);
        }

        if (!self::innerValidateMT($taskDTO, ['template', 'title', 'summary', 'header', 'footer'], count($taskDTO->source))) {
            throw new \Exception("多表格模式下 template、title、summary、header、footer、source 字段的元素个数必须一致（除非没有设置该字段）", ErrCode::PARAM_VALIDATE_FAIL);
        }
    }

    private static function formatMT(TaskDTO $taskDTO)
    {
        self::innerFormatMT($taskDTO, ['template', 'title', 'summary', 'header', 'footer']);
    }

    private static function innerFormatMT(TaskDTO $taskDTO, array $fields)
    {
        foreach ($fields as $field) {
            if (!property_exists($taskDTO, $field)) {
                continue;
            }

            // 如果为空或者没有设置，统一设置成空数组
            $val = $taskDTO->{$field};
            if (!$val) {
                $taskDTO->{$field} = [];
            } else {
                $taskDTO->{$field} = is_string($val) ? json_decode($taskDTO->{$field}, true) : $val;
            }
        }
    }

    private static function innerValidateMT(TaskDTO $dto, array $fields, int $base): bool
    {
        foreach ($fields as $field) {
            if (count($dto->{$field}) && count($dto->{$field}) != $base) {
                return false;
            }
        }

        return true;
    }

    private static function buildSource(TaskDTO $taskDTO, string $targetType): ISource
    {
        if (!$taskDTO->source) {
            throw new \Exception("数据源错误", ErrCode::SOURCE_FORMAT_ERR);
        }

        $source = null;
        switch (strtolower($targetType)) {
            case 'csv':
            case 'excel': 
            default:
               $source = new CSVSource(
                    $taskDTO->source,
                    File::join(Config::getInstance()->getConf('local_file_base_dir'), $taskDTO->id),
                    $taskDTO->id,
                    intval($taskDTO->step) ?: CSVSource::STEP_DEFAULT,
                   $taskDTO->interval ?: CSVSource::DEFAULT_INTERVAL,
                   $taskDTO->multiType == ExcelTarget::MT_SINGLE ? CSVSource::SOURCE_TYPE_SIMPLE : CSVSource::SOURCE_TYPE_MULTI
                );
        }
        
        return $source;
    }

    private static function buildTarget(TaskDTO $taskDTO): Target
    {
        if (!$taskDTO->template) {
            throw new \Exception("模板异常", ErrCode::TPL_FMT_ERR);
        }

        $baseDir = File::join(Config::getInstance()->getConf('local_file_base_dir'), $taskDTO->id);
        switch ($taskDTO->type ?: Target::TYPE_CSV) {
            case Target::TYPE_CSV:
                return new CSVTarget($baseDir, $taskDTO->fileName ?: '');
            case Target::TYPE_EXCEL:
                $excel = new ExcelTarget($baseDir, $taskDTO->fileName ?: '', $taskDTO->multiType);
                $excel->setMeta(
                    [
                        'templates' => $taskDTO->template ?: null,
                        'titles' => $taskDTO->title ?: '',
                        'summaries' => $taskDTO->summary ?: '',
                        'headers' => $taskDTO->header ?: '',
                        'footers' => $taskDTO->footer ?: '',
                        'headers_align' => $taskDTO->headerAlign ?: 'right',
                        'footers_align' => $taskDTO->footerAlign ?: 'right',
                        'default_width' => $taskDTO->defaultWidth,
                        'default_height' => $taskDTO->defaultHeight,
                        'rowoffset' => $taskDTO->rowoffset,
                    ]
                );
                return $excel;
            default:
                throw new Exception("不支持的文件类型", ErrCode::FILE_TYPE_ERR);
        }
    }
}
