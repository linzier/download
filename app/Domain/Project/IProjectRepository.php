<?php

namespace App\Domain\Project;

interface IProjectRepository
{
    public function addProject(Project $project);

    public function updateProject(Project $project);

    public function deleteProject(string $id);

    public function getProjectById(string $id): ?Project;

    public function getProjectByName(string $name): ?Project;

    public function addGroup(Group $group);

    public function updateGroup(Group $group);

    public function getGroupById(string $id): ?Group;

    public function getGroupByName(string $name): ?Group;
}
