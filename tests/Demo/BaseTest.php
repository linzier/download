<?php

namespace Test\Demo;

use PHPUnit\Framework\TestCase;

/**
 * Basic Usage
 * Class BaseTest
 * @package Test
 */
class BaseTest extends TestCase
{
    public function testArray()
    {
        $arr = [];

        $this->assertEmpty($arr);
        $arr[] = 3;
        $this->assertEquals(1, count($arr));
    }

//    public function testSomething()
//    {
//        $this->markTestIncomplete("This test has not been implemented yet");
//    }
}
