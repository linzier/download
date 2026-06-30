<?php

namespace Test\File\Template\Excel;

use App\Domain\File\Template\Excel\ColHead;
use App\Domain\File\Template\Excel\ColHeadParser;
use PHPUnit\Framework\TestCase;

/**
 * Class BaseTest
 * @package Test
 */
class ColHeadParserTest extends TestCase
{
    public function testParse()
    {
        $cfgStr = '[{
            "name": "money",
            "title": "Title 1",
            "type": "auto",
            "width": 50,
            "height": 40,
            "align": "left",
            "color": "FFFEFE",
            "bold": true,
            "bg_color": "white",
            "children": [
                {
                    "name": "money",
                    "title": "Title 21",
                    "type": "auto",
                    "width": 60,
                    "height": 50,
                    "align": "left",
                    "color": "FFFEFE",
                    "bold": true,
                    "bg_color": "white",
                    "children": [{
                        "name": "money",
                        "title": "Title 3",
                        "type": "auto",
                        "width": 70,
                        "height": 60,
                        "align": "right",
                        "color": "FFFEFE",
                        "bold": true,
                        "bg_color": "white"
                    }]
                },
                {
                    "name": "money",
                    "title": "Title 22",
                    "type": "auto",
                    "width": 60,
                    "height": 50,
                    "align": "left",
                    "color": "FFFEFE",
                    "bold": true,
                    "bg_color": "white",
                    "children": [{
                        "name": "money",
                        "title": "Title 3",
                        "type": "auto",
                        "width": 70,
                        "height": 60,
                        "align": "right",
                        "color": "FFFEFE",
                        "bold": true,
                        "bg_color": "white"
                    }]
                }
            ]
        }]';
        $cfg = json_decode($cfgStr, true);
        /**
         * @var ColHead
         */
        $col = ColHeadParser::getInstance()->parse($cfg);
        $this->assertEquals($col->title(), 'Title 1');
        $this->assertEquals(count($col->children()), 2);
        $this->assertEquals($col->children()[0]->title(), 'Title 21');
        $this->assertEquals($col->children()[0]->children()[0]->title(), 'Title 3');
    }
}
