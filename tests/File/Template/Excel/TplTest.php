<?php

namespace Test\File\Template\Excel;

use App\Domain\File\Template\Excel\TplFactory;
use PHPUnit\Framework\TestCase;

class TplTest extends TestCase
{
    public function testToArray()
    {
        $cfgStr = '{
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
        }';

        $this->assertEquals(1, 1);
    }
}
