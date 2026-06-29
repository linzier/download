<?php

namespace App\Domain\Project;

use WecarSwoole\Container;
use WecarSwoole\Entity;
use WecarSwoole\ID\IIDGenerator;

/**
 * 项目组
 */
class Group extends Entity
{
    protected $id;
    protected $name;

    public function __construct($name)
    {
        $this->id = Container::get(IIDGenerator::class)->id();
        $this->name = $name;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
