<?php

namespace App\Domain\Task;

use App\Domain\Project\IProjectRepository;
use App\ErrCode;
use App\Foundation\DTO\TaskDTO;
use WecarSwoole\Exceptions\Exception;

/**
 * Task Service
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
     * Create a new task
     * @return string Task id
     */
    public function create(TaskDTO $taskDTO): Task
    {
        $task = TaskFactory::create($taskDTO);

        // Persist to database
        $this->taskRepository->addTask($task);

        return $task;
    }

    public function getTask(string $taskId): ?Task
    {
        return $this->taskRepository->getTaskById($taskId);
    }

    /**
     * Switch task status
     */
    public function switchStatus(Task $task, int $newStatus, string $failedReason = '')
    {
        $oldStatus = $task->status();
        $task->switchStatus($newStatus, $failedReason);
        
        if (!$this->taskRepository->changeTaskStatus($task, $oldStatus)) {
            throw new Exception("Failed to change task status: storage failure. {$task->id()}: {$oldStatus} -> {$newStatus}", ErrCode::INVALID_STATUS_OP);
        }
    }
}
