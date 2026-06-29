<?php

namespace App\Http\Controllers\V1;

use App\Domain\Task\ITaskRepository;
use App\Domain\Task\Merchant;
use App\Domain\Task\Task as DlTask;
use App\Domain\Task\TaskService;
use App\ErrCode;
use App\Foundation\DTO\TaskDTO;
use App\Processor\TaskManager;
use EasySwoole\EasySwoole\Config;
use WecarSwoole\Container;
use WecarSwoole\Http\Controller;

class Task extends Controller
{
    use ParamUtil;

    protected function validateRules(): array
    {
        return [
            'deliver' => [
                'source_url' => ['optional', 'url', 'lengthMin' => 2, 'lengthMax' => 100000],
                'name' => ['required', 'lengthMin' => 2, 'lengthMax' => 60],
                'project_id' => ['required', 'lengthMax' => 40],
                'file_name' => ['lengthMax' => 120],
                'type' => ['inArray' => [null, 'csv', 'excel']],
                'callback' => ['lengthMax' => 300],
                'operator_id' => ['lengthMax' => 120],
                'merchant_type' => ['required', 'integer'],
                'merchant_id' => ['required', 'integer'],
                'template' => ['required', 'lengthMax' => 100000],
                'title' => ['lengthMax' => 200],
                'summary' => ['lengthMax' => 8000],
                'interval' => ['optional', 'integer', 'min' => 100, 'max' => 3000],
                'rowoffset' => ['optional', 'integer', 'min' => 0,],
            ],
            'deliverMultiple' => [
                'source_data' => ['optional'],
                'source' => ['optional'],
                'name' => ['required', 'lengthMin' => 2, 'lengthMax' => 60],
                'project_id' => ['required', 'lengthMax' => 40],
                'file_name' => ['lengthMax' => 120],
                'type' => ['inArray' => [null, 'excel']],
                'callback' => ['lengthMax' => 300],
                'operator_id' => ['lengthMax' => 120],
                'merchant_type' => ['required', 'integer'],
                'merchant_id' => ['required', 'integer'],
                'interval' => ['optional', 'integer', 'min' => 100, 'max' => 3000],
                'template' => ['required', 'lengthMax' => 100000],
                'rowoffset' => ['optional', 'integer', 'min' => 0,],
            ],
            'one' => [
                'task_id' => ['required', 'lengthMax' => 100],
            ],
            'list' => [
                'project_ids' => ['required', 'lengthMax' => 1000],
                'page' => ['required', 'integer', 'min' => 0],
                'page_size' => ['optional', 'integer', 'max' => 100],
            ],
            'delete' => [
                'task_ids' => ['required', 'lengthMax' => 50000, 'lengthMin' => 5,],
                'project_ids' => ['required', 'lengthMax' => 1000, 'lengthMin' => 5,],
                'operator_id' => ['optional', 'lengthMax' => 200,],
            ]
        ];
    }

    /**
     * 投递任务
     */
    public function deliver()
    {
        $task = Container::get(TaskService::class)->create(new TaskDTO(self::dealParams($this->params())));
        TaskManager::getInstance()->deliver($task);
        $this->return(['task_id' => $task->id()]);
    }

    /**
     * 投递任务：多表格模式
     */
    public function deliverMultiple()
    {
        $params = self::dealParams(array_merge(['multi_type' => 'page'], $this->params(), ['type' => 'excel']));
        $task = Container::get(TaskService::class)->create(new TaskDTO($params));
        TaskManager::getInstance()->deliver($task);
        $this->return(['task_id' => $task->id()]);
    }

    /**
     * 查询某个任务详情
     */
    public function one()
    {
        if (!$taskDTO = Container::get(ITaskRepository::class)->getTaskDTOById($this->params('task_id'))) {
            return $this->return([], ErrCode::TASK_NOT_EXISTS, '任务不存在');
        }

        $taskArr = $taskDTO->toArray(true, true, false, ['sourceUrl', 'sourceData', 'source', 'fileName', 'callback', 'template', 'title', 'summary', 'header', 'footer']);
        return $this->return($this->formateTask($taskArr));
    }

    /**
     * 查询任务列表
     */
    public function list()
    {
        $data = Container::get(ITaskRepository::class)->getTaskDTOs(
            explode(',', $this->params('project_ids')),
            intval($this->params('page')),
            $this->params('page_size') ? intval($this->params('page_size')) : 20,
            $this->formateStatus(array_filter(explode(',', $this->params('status')))),
            $this->params('operator_id') ?: '',
            $this->params('merchant_id') !== null ? new Merchant($this->params('merchant_id'), $this->params('merchant_type')) : null,
            $this->params('task_name') ?: ''
        );

        if (!$data['total']) {
            return $this->return($data);
        }

        $data['data'] = array_map(function (TaskDTO $taskDTO) {
            $taskArr = $taskDTO->toArray(true, true, false, ['sourceUrl', 'sourceData', 'source', 'fileName', 'callback', 'template', 'title', 'summary', 'header', 'footer']);
            return $this->formateTask($taskArr);
        }, $data['data']);

        return $this->return($data);
    }

    /**
     * 删除任务
     */
    public function delete()
    {
        Container::get(ITaskRepository::class)->delete(
            explode(',', $this->params('task_ids')),
            explode(',', $this->params('project_ids')),
            $this->params('operator_id') ?: ''
        );
        return $this->return();
    }

    /**
     * 处理状态：外部只支持传入 3,4,6
     */
    private function formateStatus(array $status): array
    {
        if (!$status) {
            return [];
        }

        if (in_array(DlTask::STATUS_DOING, $status)) {
            $status = array_merge($status, [DlTask::STATUS_TODO, DlTask::STATUS_ENQUEUED, DlTask::STATUS_FAILED]);
        }

        return array_unique(array_filter($status));
    }

    private function formateTask(array $task): array
    {
        if (time() - $task['ctime'] >= Config::getInstance()->getConf('download_expire')) {
            $task['status'] = DlTask::STATUS_EXPIRED;
        }

        // 状态处理
        $task['status'] = [
            DlTask::STATUS_TODO => DlTask::STATUS_DOING,
            DlTask::STATUS_ENQUEUED => DlTask::STATUS_DOING,
            DlTask::STATUS_DOING => DlTask::STATUS_DOING,
            DlTask::STATUS_FAILED => DlTask::STATUS_DOING,
            DlTask::STATUS_SUC => DlTask::STATUS_SUC,
            DlTask::STATUS_ERR => DlTask::STATUS_ERR,
            DlTask::STATUS_EXPIRED => DlTask::STATUS_EXPIRED,
        ][$task['status']];
        $task['status_name'] = [
            DlTask::STATUS_DOING => '处理中',
            DlTask::STATUS_SUC => '处理成功',
            DlTask::STATUS_ERR => '处理失败',
            DlTask::STATUS_EXPIRED => '已过期',
        ][$task['status']];

        return $task;
    }
}
