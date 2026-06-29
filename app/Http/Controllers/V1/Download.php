<?php

namespace App\Http\Controllers\V1;

use App\Domain\Task\TaskService;
use App\Domain\Transfer\TransferService;
use App\ErrCode;
use App\Foundation\DTO\TaskDTO;
use App\Http\Service\DownloadService;
use WecarSwoole\Container;
use WecarSwoole\Http\Controller;

class Download extends Controller
{
    use ParamUtil;

    protected function validateRules(): array
    {
        return [
            'asyncGetData' => [
                'task_id' => ['required', 'lengthMax' => 100],
            ],
            'getDownloadUrl' => [
                'task_id' => ['required', 'lengthMax' => 100],
            ],
            'getData' => [
                'ticket' => ['required', 'lengthMax' => 100],
            ],
            'syncGetData' => [
                'source_url' => ['optional', 'url', 'lengthMin' => 2, 'lengthMax' => 5000],
                'name' => ['required', 'lengthMin' => 2, 'lengthMax' => 60],
                'project_id' => ['required', 'lengthMax' => 40],
                'file_name' => ['lengthMax' => 120],
                'type' => ['inArray' => [null, 'csv', 'excel']],
                'callback' => ['lengthMax' => 300],
                'operator_id' => ['lengthMax' => 120],
                'template' => ['required', 'lengthMax' => 100000],
                'title' => ['lengthMax' => 200],
                'summary' => ['lengthMax' => 8000],
                'interval' => ['optional', 'integer', 'min' => 100, 'max' => 3000],
                'rowoffset' => ['optional', 'integer', 'min' => 0,],
            ],
            'syncGetDataMultiple' => [
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
        ];
    }

    /**
     * 取数据
     * 后端鉴权后下载数据
     * 注意：调用端需要考虑大文件下载情况，此时如果调用端使用诸如 file_get_contents 将数据一次全部放到内存中
     * 可能会导致内存溢出，此时应该采用 curl 的 CURLOPT_WRITEFUNCTION 将数据写入到本地文件中
     */
    public function asyncGetData()
    {
        Container::get(DownloadService::class)->download($this->params('task_id'), $this->response());
    }

    /**
     * 获取临时下载 url
     */
    public function getDownloadUrl()
    {
        $task = Container::get(TaskService::class)->getTask($this->params('task_id'));
        if (!$task) {
            return $this->return([], ErrCode::TASK_NOT_EXISTS, "任务{$this->params('task_id')}不存在");
        }

        $this->return(['url' => Container::get(TransferService::class)->buildDownloadUrlNew($task)]);
    }

    /**
     * 前端通过临时下载 url 下载数据
     * 无需鉴权，该 url 通过 getDownloadUrl 生成
     * @deprecated 该方法已经废弃，现在异步下载是直接通过访问 OSS 的临时 url 下载，不再经过下载中心
     */
    public function getData()
    {
        Container::get(DownloadService::class)->downloadWithTicket($this->params('ticket'), $this->response());
    }

    /**
     * 同步下载数据
     * 相当于投递任务和取回数据一体化，用于小批量数据下载
     */
    public function syncGetData()
    {
        Container::get(DownloadService::class)
        ->syncDownload(new TaskDTO(self::dealParams($this->params())), $this->response());
    }

    /**
     * 同步下载多表数据
     */
    public function syncGetDataMultiple()
    {
        $params = self::dealParams(array_merge(['multi_type' => 'page'], $this->params(), ['type' => 'excel']));
        Container::get(DownloadService::class)
        ->syncDownload(new TaskDTO($params), $this->response());
    }
}
