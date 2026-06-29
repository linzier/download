<?php

include "./base.php";

$a = [
    'row' => [
        [
            'title' => '云研发',
            'children' => [
                [
                    'name' => 'front_end',
                    'title' => '前端',
                    'row_count' => 2,
                ],
                [
                    'name' => 'back_end',
                    'title' => '后端',
                    'row_count' => 4,
                ],
            ]
        ],
        [
            'title' => 'OS及智能设备',
            'children' => [
                [
                    'title' => 'OS',
                    'name' => 'os',
                    'row_count' => 3,
                ],
                [
                    'title' => '智能设备',
                    'children' => [
                        [
                            'name' => 'pos',
                            'title' => '手持终端',
                            'row_count' => 2,
                        ],
                        [
                            'name' => 'screen',
                            'title' => '大屏',
                            'row_count' => 3,
                        ],
                    ]
                ]
            ]
        ]
    ],
    'col' => [
        [
            'title' => '人员',
            'children' => [
                [
                    'name' => 'name',
                    'title' => '姓名',
                    'type' => 'string',
                    'color' => 'red',
                    "width" => -1
                ],
                [
                    'title' => '其它',
                    'children' => [
                        [
                            'name' => 'age',
                            'title' => '年龄',
                            'type' => 'number',
                        ],
                        [
                            'name' => 'sex',
                            'title' => '性别',
                            'type' => 'string',
                            'width' => 8,
                        ],
                        [
                            'title' => '爱好',
                            'children' => [
                                [
                                    'name' => 'love_in',
                                    'title' => '室内',
                                ],
                                [
                                    'title' => '室外',
                                    'children' => [
                                        [
                                            'name' => 'love_out_land',
                                            'title' => '陆地',
                                        ],
                                        [
                                            'name' => 'love_out_sky',
                                            'title' => '空中',
                                        ],
                                    ]
                                ],
                            ]
                        ]
                    ]
                ]
            ]
        ],
        [
            'title' => '住址',
            'children' => [
                [
                    'name' => 'city',
                    'title' => '城市'
                ],
                [
                    'title' => '小区',
                    'children' => [
                        [
                            'name' => 'area',
                            'title' => '区域',
                        ],
                        [
                            'name' => 'building',
                            'title' => '楼盘',
                        ]
                    ]
                ]
            ]
        ],
    ],
    'col_' => [
        'name' => '名字',
        'age' => '年龄',
        'sex' => '性别',
        'love_in' => '室内爱好',
        'love_out_land' => '室外陆地爱好',
        'love_out_sky' => '室外空中爱好',
        'city' => '城市',
        'area' => '区域',
        'building' => '小区',
    ]
];


$d = '{"name":"abc"}';
$s = @unserialize($d);
