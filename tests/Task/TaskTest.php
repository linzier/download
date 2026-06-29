<?php

namespace Test\Task;

use App\Domain\Project\Group;
use App\Domain\Project\IProjectRepository;
use App\Domain\Project\Project;
use App\Domain\Task\Task;
use App\Domain\Task\TaskFactory;
use App\ErrCode;
use App\Foundation\DTO\TaskDTO;
use EasySwoole\Utility\Random;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophet;
use WecarSwoole\ID\IIDGenerator;

class TaskTest extends TestCase
{
    public function setUp()
    {
        $this->prophet = new Prophet();

        parent::setUp();
    }

    private function createTask()
    {
        $repos = $this->prophet->prophesize(IProjectRepository::class);
        $proj = $this->prophet->prophesize(Project::class);
        $idGen = $this->prophet->prophesize(IIDGenerator::class);
        $idGen->id()->willReturn('123456789');
        $repos->getProjectById("123132423")->willReturn($proj->reveal());
        $dto = new TaskDTO([
            'name' => 'test',
            'source_url' => 'http://www.ex.com',
            'project_id' => '123132423',

        ]);
        return TaskFactory::create($dto, $repos->reveal(), $idGen->reveal());
    }

    /**
     * Task 对象的创建
     */
    public function testCreate()
    {
        $task = $this->createTask();
        $this->assertEquals($task->status(), Task::STATUS_TODO);
        $this->assertEquals($task->objectFile()->type(), 'csv');
    }

    /**
     * 状态切换
     */
    public function testSwitchStatus()
    {
        $task = $this->createTask();
        $this->assertEquals($task->status(), Task::STATUS_TODO);

        $this->expectExceptionCode(ErrCode::INVALID_STATUS_OP);
        $task->switchStatus(Task::STATUS_SUC);
    }
}
