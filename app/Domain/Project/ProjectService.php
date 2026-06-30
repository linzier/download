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
     * Create a new project group
     * @return Group
     */
    public function createGroup(string $groupName): Group
    {
        if (!$groupName) {
            throw new Exception("Project group name cannot be empty", ErrCode::EMPTY_PARAMS);
        }

        if ($this->projectRepository->getGroupByName($groupName)) {
            throw new Exception("This project group already exists", ErrCode::GROUP_AREADY_EXISTS);
        }

        $group = new Group($groupName);
        $this->projectRepository->addGroup($group);

        return $group;
    }

    /**
     * Create a new project
     * @return Project
     */
    public function createProject(string $projectName, string $groupId): Project
    {
        if (!$projectName) {
            throw new Exception("Please provide a project name", ErrCode::EMPTY_PARAMS);
        }

        if ($this->projectRepository->getProjectByName($projectName)) {
            throw new Exception("This project name already exists", ErrCode::PROJ_AREADY_EXISTS);
        }

        if (!$group = $this->projectRepository->getGroupById($groupId)) {
            throw new Exception("Project group does not exist", ErrCode::GROUP_NOT_EXISTS);
        }

        $project = new Project($projectName, $group);
        $this->projectRepository->addProject($project);

        return $project;
    }
}
