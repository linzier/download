<?php

namespace App\Foundation\Repository\Project;

use App\Domain\Project\IProjectRepository;
use WecarSwoole\Repository\MySQLRepository;
use App\Domain\Project\Project;
use App\Domain\Project\Group;
use Exception;
use ReflectionClass;

class MySQLProjectRepository extends MySQLRepository implements IProjectRepository
{
    protected function dbAlias(): string
    {
        return 'download';
    }

    public function addProject(Project $project)
    {
        $this->query
        ->insert('project')
        ->values(
            [
                'id' => $project->id(),
                'name' => $project->name(),
                'group_id' => $project->group()->id(),
                'ctime' => $project->createTime,
                'is_deleted' => 0,
            ]
        )
        ->execute();

        if (!$this->query->affectedRows()) {
            throw new Exception("保存项目信息失败");
        }
    }

    public function updateProject(Project $project)
    {
        $this->query
        ->update('project')
        ->set(
            [
                'name' => $project->name(),
                'group_id' => $project->group()->id(),
            ]
        )
        ->where(['id' => $project->id()])
        ->execute();
    }

    public function deleteProject(string $id)
    {
        if (!$id) {
            return;
        }

        $this->query
        ->update('project')
        ->set(['is_deleted' => 1])
        ->where(['id' => $id])
        ->execute();
    }

    public function getProjectById(string $id): ?Project
    {
        $info = $this->query
        ->select('p.id proj_id,p.name proj_name,p.ctime proj_ctime,g.id group_id,g.name group_name')
        ->from('project p')
        ->join('project_group g', "p.group_id=g.id")
        ->where(['p.is_deleted' => 0, 'p.id' => $id])
        ->one();

        return $this->assembleProjectFromArray($info);
    }

    public function getProjectByName(string $name): ?Project
    {
        $info = $this->query
        ->select('p.id proj_id,p.name proj_name,p.ctime proj_ctime,g.id group_id,g.name group_name')
        ->from('project p')
        ->join('project_group g', "p.group_id=g.id")
        ->where(['p.is_deleted' => 0, 'p.name' => $name])
        ->one();

        return $this->assembleProjectFromArray($info);
    }

    public function addGroup(Group $group)
    {
        $this->query
        ->insert('project_group')
        ->values(
            [
                'id' => $group->id(),
                'name' => $group->name(),
                'ctime' => time(),
                'is_deleted' => 0,
            ]
        )
        ->execute();

        if (!$this->query->affectedRows()) {
            throw new Exception("保存项目组信息失败");
        }
    }

    public function updateGroup(Group $group)
    {
        $this->query
        ->update('project_group')
        ->set(['name' => $group->name()])
        ->where(['id' => $group->id()])
        ->execute();
    }

    public function getGroupById(string $id): ?Group
    {
        $info = $this->query->select('id,name')->from('project_group')->where(['id' => $id, 'is_deleted' => 0])->one();
        return $this->assembleGroupFromArray($info);
    }

    public function getGroupByName(string $name): ?Group
    {
        $info = $this->query->select('id,name')->from('project_group')->where(['name' => $name, 'is_deleted' => 0])->one();
        return $this->assembleGroupFromArray($info);
    }

    protected function assembleProjectFromArray(array $info): ?Project
    {
        if (!$info) {
            return null;
        }

        $project = (new ReflectionClass(Project::class))->newInstanceWithoutConstructor();

        $project->id = $info['proj_id'];
        $project->name = $info['proj_name'];
        $project->createTime = $info['proj_ctime'];
        $project->group = $this->assembleGroupFromArray(['id' => $info['group_id'], 'name' => $info['group_name']]);

        return $project;
    }

    protected function assembleGroupFromArray(array $info): ?Group
    {
        if (!$info) {
            return null;
        }

        $group = (new ReflectionClass(Group::class))->newInstanceWithoutConstructor();
        $group->id = $info['id'];
        $group->name = $info['name'];

        return $group;
    }
}
