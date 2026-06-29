<?php

namespace App\Domain\Project;

use WecarSwoole\Container;
use WecarSwoole\Entity;
use WecarSwoole\ID\IIDGenerator;

/**
 * 项目
 */
class Project extends Entity
{
    protected $id;
    protected $group;
    protected $name;
    protected $createTime;

    public function __construct(string $name, Group $group)
    {
        $this->id = Container::get(IIDGenerator::class)->id();
        $this->name = $name;
        $this->group = $group;
        $this->createTime = time();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function group(): Group
    {
        return $this->group;
    }
}
