<?php

namespace Test\Demo;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Test\Demo\Sample\Observer;
use Test\Demo\Sample\Subject;

/**
 * Mocks
 * Unlike Stubs, when using mocks, the test target is the mock itself (e.g., verifying whether a certain method on the mock was called in a specific way).
 * Class MockTest
 * @package Test\Demo
 */
class MockTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $mock;

    public function setUp()
    {
        $this->mock = $this->getMockBuilder(Observer::class)->setMethods(['update'])->getMock();

        parent::setUp();
    }

    public function testUpdateBeInvoked()
    {
        $this->mock->expects($this->once())
            ->method('update')
            ->with($this->equalTo('something'));

        $subject = new Subject('name');
        $subject->attach($this->mock);
        $subject->doSomething();
    }
}
