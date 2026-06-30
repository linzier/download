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
 * Factory: create task objects
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
            throw new Exception("Failed to create task: project does not exist", ErrCode::PROJ_NOT_EXISTS);
        }

        // No uniqueness check is performed on externally provided IDs; the caller is responsible for ensuring uniqueness
        $id = $taskDTO->id ?? Container::get(IIDGenerator::class)->id();
        $taskDTO->id = $id;

        // multiType
        $taskDTO->multiType = $taskDTO->multiType ?? ExcelTarget::MT_SINGLE;

        if ($taskDTO->multiType != ExcelTarget::MT_SINGLE) {
            // Validate multi-table data format
            self::formatAndValidateMultiTableData($taskDTO);
        }
        
        // Source
        $source = self::buildSource($taskDTO, $taskDTO->type ?? 'csv');
        // Target
        $target = self::buildTarget($taskDTO);
        // Callback
        $callback = new URI($taskDTO->callback ?: '');

        // Create Task object based on DTO
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
            // Data from the storage layer; set additional properties
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
     * The number of first-dimension elements in the template, title, summary, header, footer, and source arrays must be the same and in corresponding order
     * @param TaskDTO $taskDTO
     * @throws \Exception
     */
    private static function formatAndValidateMultiTableData(TaskDTO $taskDTO)
    {
        self::formatMT($taskDTO);

        if (!$taskDTO->source) {
            throw new \Exception("Missing data source", ErrCode::TPL_FMT_ERR);
        }

        if (!self::innerValidateMT($taskDTO, ['template', 'title', 'summary', 'header', 'footer'], count($taskDTO->source))) {
            throw new \Exception("In multi-table mode, the number of elements in template, title, summary, header, footer, and source fields must be consistent (unless the field is not set)", ErrCode::PARAM_VALIDATE_FAIL);
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

            // If empty or not set, default to an empty array
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
            throw new \Exception("Data source error", ErrCode::SOURCE_FORMAT_ERR);
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
            throw new \Exception("Template error", ErrCode::TPL_FMT_ERR);
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
                throw new Exception("Unsupported file type", ErrCode::FILE_TYPE_ERR);
        }
    }
}
