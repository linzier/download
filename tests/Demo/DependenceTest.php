<?php

namespace Test\Demo;

use PHPUnit\Framework\TestCase;

/**
 * Test Case Dependency Declaration
 * Note: It is best to avoid writing tests with dependencies between them.
 * Class DependenceTest
 * @see https://phpunit.readthedocs.io/zh_CN/latest/writing-tests-for-phpunit.html
 * @package Test\Demo
 */
class DependenceTest extends TestCase
{
    public function testArray()
    {
        $arr = [];
        $this->assertEmpty($arr);

        return $arr;
    }

    /**
     * Test dependency
     * @param array $arr
     * @depends testArray
     * @return array
     */
    public function testDependence(array $arr)
    {
        array_push($arr, 4);
        $this->assertEquals(1, count($arr));

        return $arr;
    }

    /**
     * @param array $arr1
     * @param array $arr2
     * @depends testArray
     * @depends testDependence
     */
    public function testDependenceMore(array $arr1, array $arr2)
    {
        $this->assertEquals(1, count($arr2) - count($arr1));
    }
}
