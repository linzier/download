<?php

namespace App\Domain\Project;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

class ProjectService
{
    protected $projectRepository;

    public function __construct(IProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    /**
     * 创建项目组
     * @return Group
     */
    public function createGroup(string $groupName): Group
    {
        if (!$groupName) {
            throw new Exception("项目组名称不能为空", ErrCode::EMPTY_PARAMS);
        }

        if ($this->projectRepository->getGroupByName($groupName)) {
            throw new Exception("该项目组已经存在", ErrCode::GROUP_AREADY_EXISTS);
        }

        $group = new Group($groupName);
        $this->projectRepository->addGroup($group);

        return $group;
    }

    /**
     * 创建新项目
     * @return Project
     */
    public function createProject(string $projectName, string $groupId): Project
    {
        if (!$projectName) {
            throw new Exception("请提供项目名称", ErrCode::EMPTY_PARAMS);
        }

        if ($this->projectRepository->getProjectByName($projectName)) {
            throw new Exception("该项目名称已经存在", ErrCode::PROJ_AREADY_EXISTS);
        }

        if (!$group = $this->projectRepository->getGroupById($groupId)) {
            throw new Exception("项目组不存在", ErrCode::GROUP_NOT_EXISTS);
        }

        $project = new Project($projectName, $group);
        $this->projectRepository->addProject($project);

        return $project;
    }
}
