<?php

namespace Test\Demo;

use PHPUnit\Framework\TestCase;

/**
 * Data Provider
 * Class DataProviderTest
 * @package Test\Demo
 */
class DataProviderTest extends TestCase
{
    /**
     * Test data supply
     * @param $a
     * @param $b
     * @param $expected
     * @dataProvider dataProvider
     */
    public function testDataProvider($param1, $param2, $expected)
    {
        $this->assertEquals($expected, $param1 + $param2);
    }

    /**
     * The data provider must return a two-dimensional array or an iterator whose elements are arrays.
     * String keys can be used to make it more semantic.
     * Each element in the second dimension corresponds to a parameter of the receiving method.
     * @return array
     */
    public function dataProvider()
    {
        return [
            [1, 2, 3],
            [2, 3, 5],
            ['param1' => 4, 'param2' => 6, 'expected' => 10]
        ];
    }
}
