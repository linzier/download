<?php

namespace App\Domain\Task;

use App\Domain\Project\IProjectRepository;
use App\ErrCode;
use App\Foundation\DTO\TaskDTO;
use WecarSwoole\Exceptions\Exception;

/**
 * 任务服务
 */
class TaskService
{
    private $taskRepository;

    public function __construct(ITaskRepository $taskRepository, IProjectRepository $projectRepository)
    {
        $this->taskRepository = $taskRepository;
        $this->projectRepository = $projectRepository;
    }

    /**
     * 创建新任务
     * @return string 任务 id
     */
    public function create(TaskDTO $taskDTO): Task
    {
        $task = TaskFactory::create($taskDTO);

        // 存储到数据库
        $this->taskRepository->addTask($task);

        return $task;
    }

    public function getTask(string $taskId): ?Task
    {
        return $this->taskRepository->getTaskById($taskId);
    }

    /**
     * 切换任务状态
     */
    public function switchStatus(Task $task, int $newStatus, string $failedReason = '')
    {
        $oldStatus = $task->status();
        $task->switchStatus($newStatus, $failedReason);
        
        if (!$this->taskRepository->changeTaskStatus($task, $oldStatus)) {
            throw new Exception("修改任务状态失败：存储失败。{$task->id()}：{$oldStatus} -> {$newStatus}", ErrCode::INVALID_STATUS_OP);
        }
    }
}
