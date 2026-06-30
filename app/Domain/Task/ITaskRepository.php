<?php

namespace App\Domain\Task;

use App\Foundation\DTO\DBTaskDTO;

interface ITaskRepository
{
    public function addTask(Task $task);

    public function getTaskById(string $id): ?Task;

    /**
     * Get task detail DTO by task ID
     */
    public function getTaskDTOById(string $id): ?DBTaskDTO;

    /**
     * Get the task list under the given project IDs, ordered by task creation time descending
     * @param array $projectIds Project ID list (can query multiple projects at once)
     * @param array $status Task status list; empty array means all statuses
     * @param int $page Pagination, starting from 0
     * @param int $pageSize Number of items per page
     * @param mixed $operatorId Operator
     * @param Merchant $merchant Merchant
     * @param string $taskName Task name
     * @return Array Array of DBTaskDTO
     */
    public function getTaskDTOs(array $projectIds, int $page, int $pageSize = 20, array $status = [], $operatorId = '', Merchant $merchant = null, $taskName = ''): Array;

    /**
     * Query the list of tasks that may need retrying
     * @param array $status Status list
     * @param int $startTime Task creation time start
     * @param int $endTime Task creation time end
     * @param int $maxRetry Maximum retry limit
     * @return array Array of DBTaskDTO objects
     */
    public function getTaskDTOsToRetry(array $status, int $startTime, int $endTime, int $maxRetry): Array;

    /**
     * Change task status
     * @return bool Whether the change was successful
     */
    public function changeTaskStatus(Task $task, int $oldStatus): bool;

    /**
     * Archive data before $beforeTime
     * @param int $beforeTime Archive data before this time (unix timestamp)
     * @param bool $optimize Whether to run OPTIMIZE TABLE to defragment
     */
    public function fileTask(int $beforeTime, bool $optimize);

    /**
     * Query task status
     */
    public function getTaskStatus(string $taskId): int;

    /**
     * Delete tasks
     * Can delete multiple tasks at once
     * @param array $taskIds List of task IDs to delete
     * @param array $projectIds Restrict deletion to tasks belonging to these projects only
     * @param string|int $operatorId Restrict deletion to tasks created by this operator only
     */
    public function delete(array $taskIds, array $projectIds, $operatorId = '');
}
