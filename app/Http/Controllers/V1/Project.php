<?php

namespace App\Http\Controllers\V1;

use App\Domain\Project\ProjectService;
use WecarSwoole\Container;
use WecarSwoole\Http\Controller;

class Project extends Controller
{
    protected function validateRules(): array
    {
        return [
            'createProject' => [
                'name' => ['required', 'lengthMax' => 80],
                'group_id' => ['required', 'lengthMax' => 80],
            ],
            'createGroup' => [
                'name' => ['required', 'lengthMax' => 80],
            ],
        ];
    }

    /**
     * 创建项目
     */
    public function createProject()
    {
        $project = Container::get(ProjectService::class)->createProject($this->params('name'), $this->params('group_id'));
        $this->return(['project_id' => $project->id()]);
    }

    /**
     * 创建项目组
     */
    public function createGroup()
    {
        $group = Container::get(ProjectService::class)->createGroup($this->params('name'));
        $this->return(['group_id' => $group->id()]);
    }
}
