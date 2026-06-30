<?php

namespace App\Foundation\Repository\Task;

use App\Domain\Source\CSVSource;
use App\Domain\Target\ExcelTarget;
use WecarSwoole\Repository\MySQLRepository;
use App\Domain\Task\ITaskRepository;
use App\Domain\Task\Merchant;
use App\Domain\Task\Task;
use App\Domain\Task\TaskFactory;
use App\Foundation\DTO\DBTaskDTO;
use Exception;

class MySQLTaskRepository extends MySQLRepository implements ITaskRepository
{
    protected function dbAlias(): string
    {
        return 'download';
    }

    protected const FILE_TYPE_MAP = ['csv' => 1, 'excel' => 2];

    public function addTask(Task $task)
    {
        $info = [
            'id' => $task->id(),
            'name' => $task->name(),
            'project_id' => $task->project()->id(),
            'source_data' => serialize($task->source()),// Reuse source_data field, source_url field is deprecated
            'type' => self::FILE_TYPE_MAP[$task->target()->type()],
            'file_name' => $task->target()->downloadFileName(),
            'operator_id' => $task->operator,
            'merchant_id' => $task->merchant->id(),
            'merchant_type' => $task->merchant->type(),
            'callback' => $task->callbackURI()->url(),
            'step' => $task->source()->step,
            'max_exec_time' => $task->maxExecTime(),
            'status' => $task->status,
            'retry_num' => $task->retryNum,
            'is_sync' => $task->isSync,
            'ctime' => $task->createTime,
            'utime' => $task->createTime,
            'etime' => $task->lastExecTime,
            'ftime' => $task->finishedTime,
            'stime' => $task->lastChangeStatusTime,
            'qtime' => $task->lastEnqueueTime,
        ];

        $info['obj_meta'] = serialize($task->target()->getMeta());

        $this->query->insert('task')->values($info)->execute();

        if (!$this->query->affectedRows()) {
            throw new Exception("Failed to store task");
        }
    }

    public function getTaskById(string $id): ?Task
    {
        if (!$info = $this->query->select('*')->from('task')->where(['id' => $id, 'is_deleted' => 0])->one()) {
            return null;
        }

        return TaskFactory::create($this->buildTaskDTO($info));
    }

    public function getTaskDTOById(string $id): ?DBTaskDTO
    {
        if (!$info = $this->query->select('*')->from('task')->where(['id' => $id, 'is_deleted' => 0])->one()) {
            return null;
        }

        return $this->buildTaskDTO($info);
    }

    /**
     * Currently only queries async task list
     */
    public function getTaskDTOs(
        array $projectIds,
        int $page,
        int $pageSize = 20,
        array $status = [],
        $operatorId = '',
        Merchant $merchant = null,
        $taskName = ''
    ): Array {
        // For safety, allow at most 200 results per query
        $pageSize = $pageSize > 200 ? 200 : $pageSize;

        $builder = $this->query
        ->select('*')
        ->from('task')
        ->where(['project_id' => array_filter($projectIds), 'is_deleted' => 0, 'is_sync' => 0])
        ->orderBy("incr_id desc")
        ->limit($pageSize, $page * $pageSize);

        if ($status) {
            $builder->where(['status' => array_filter($status)]);
        }

        if ($operatorId) {
            $builder->where(['operator_id' => $operatorId]);
        }

        if ($merchant) {
            $builder->where(['merchant_id' => $merchant->id(), 'merchant_type' => $merchant->type()]);
        }

        if ($taskName) {
            $builder->where("name like :task_name", ['task_name' => "%{$taskName}%"]);
        }

        $list = $builder->page();
        if (!$list['total']) {
            return $list;
        }

        // Convert to DTO
        $list['data'] = array_map(function (array $item) {
            return $this->buildTaskDTO($item);
        }, $list['data']);


        return $list;
    }

    /**
     * Query task list that may need retrying
     * @param array $status Status list
     * @param int $startTime Task creation time start
     * @param int $endTime Task creation time end
     * @param int $maxRetry Maximum retry count limit
     * @return array Array of DBTaskDTO objects
     */
    public function getTaskDTOsToRetry(array $status, int $startTime, int $endTime, int $maxRetry): Array
    {
        $list = $this->query
        ->select('*')
        ->from('task')
        ->where(['status' => $status, 'is_deleted' => 0, 'is_sync' => 0])
        ->where("retry_num<=$maxRetry and ctime>=:s_ctime and ctime<=:e_ctime", ['s_ctime' => $startTime, 'e_ctime' => $endTime])
        ->list();

        return array_map(function (array $item) {
            return $this->buildTaskDTO($item);
        }, $list);
    }

    public function changeTaskStatus(Task $task, int $oldStatus): bool
    {
        // Note: WHERE clause must include old status check for optimistic locking (prevents concurrent modifications from corrupting data)
        $this->query
        ->update('task')
        ->set([
            'status' => $task->status(),
            'failed_reason' => mb_substr($task->failedReason, 0, 2000),// Store at most 2000 characters
            'utime' => time(),
            'etime' => $task->lastExecTime,
            'ftime' => $task->finishedTime,
            'stime' => $task->lastChangeStatusTime,
            'qtime' => $task->lastEnqueueTime,
            'retry_num' => $task->retryNum,
        ])
        ->where(['id' => $task->id(), 'status' => $oldStatus])
        ->execute();

        // Add logging for issue tracking
        return $this->query->affectedRows();
    }

    public function fileTask(int $beforeTime, bool $optimize)
    {
        $this->query->execute('insert into task_history select * from task where ctime<:time', ['time' => $beforeTime]);
        $this->query->execute('delete from task where ctime<:time', ['time' => $beforeTime]);

        if ($optimize) {
            $this->query->execute("optimize table task");
        }
    }

    public function getTaskStatus(string $taskId): int
    {
        return $this->query->select('status')->from('task')->where(['id' => $taskId])->column() ?: 0;
    }

    public function delete(array $taskIds, array $projectIds, $operatorId = '')
    {
        if (!$taskIds || !$projectIds) {
            return;
        }

        $builder = $this->query
        ->update('task')
        ->set(['is_deleted' => 1, 'utime' => time()])
        ->where(['id' => $taskIds, 'project_id' => $projectIds]);

        if ($operatorId) {
            $builder->where(['operator_id' => $operatorId]);
        }

        $builder->execute();
    }

    protected function buildTaskDTO(array $info): ?DBTaskDTO
    {
        $meta = isset($info['obj_meta']) ? unserialize($info['obj_meta']) : [];

        // Backward compatibility between old and new data formats
        $source = $this->buildTmpSource($info);

        $taskDTO = new DBTaskDTO(
            array_merge(
                $info,
                [
                    'template' => $meta['templates'] ?? null,
                    'title' => $meta['titles'] ?? '',
                    'summary' => $meta['summaries'] ?? '',
                    'header' => $meta['headers'] ?? [],
                    'footer' => $meta['footers'] ?? [],
                    'header_align' => $meta['headers_align'] ?? [],
                    'footer_align' => $meta['footers_align'] ?? [],
                    'type' => array_flip(self::FILE_TYPE_MAP)[$info['type']],
                    'default_width' => $meta['default_width'] ?? 0,
                    'default_height' => $meta['default_height'] ?? 0,
                    'multi_type' => $meta['multi_type'] ?? ExcelTarget::MT_SINGLE,
                    'source' => $source->srcs(),
                    'interval' => $source->interval(),
                    'rowoffset' => $meta['rowoffset'] ?? 0,
                ]
            )
        );

        return $taskDTO;
    }

    /**
     * Backward compatibility between old and new data formats.
     * Old data uses source_url and source_data for data source URL and data source data respectively.
     * New data deprecates source_url; source_data now holds the serialized CSVSource object.
     * @param array $info
     * @return CSVSource
     * @throws Exception
     */
    protected function buildTmpSource(array $info): CSVSource
    {
        if ($info['source_url']) {
            // Presence of source_url indicates old data format
            return new CSVSource($info['source_url'], '', $info['id']);
        }

        // When source_data exists, it could be either new or old format; assume new first
        $source = @unserialize($info['source_data']);
        if ($source) {
            return $source;
        }

        $data = json_decode($info['source_data'], true);
        return new CSVSource($data, '', $info['id']);
    }
}
