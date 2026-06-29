<?php

namespace Test\File\Template\Excel;

use App\Domain\File\Template\Excel\ColHead;
use App\Domain\File\Template\Excel\RowHeadParser;
use PHPUnit\Framework\TestCase;

/**
 * Class BaseTest
 * @package Test
 */
class RowHeadParserTest extends TestCase
{
    public function testParse()
    {
        $cfgStr = '[{
            "name": "money",
            "title": "标题1",
            "bg_color": "white",
            "children": [
                {
                    "name": "money",
                    "title": "标题21",
                    "bg_color": "white",
                    "children": [{
                        "name": "money",
                        "title": "标题3",
                        "bg_color": "white"
                    }]
                },
                {
                    "name": "money",
                    "title": "标题22",
                    "bg_color": "white",
                    "children": [{
                        "name": "money",
                        "title": "标题3",
                        "bg_color": "white"
                    }]
                }
            ]
        }]';
        $cfg = json_decode($cfgStr, true);
        /**
         * @var ColHead
         */
        $row = RowHeadParser::getInstance()->parse($cfg);
        $this->assertEquals($row->title(), '标题1');
        $this->assertEquals(count($row->children()), 2);
        $this->assertEquals($row->children()[0]->title(), '标题21');
        $this->assertEquals($row->children()[0]->children()[0]->title(), '标题3');
    }
}
