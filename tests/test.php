<?php

include "./base.php";

$a = [
    'row' => [
        [
            'title' => 'Cloud R&D',
            'children' => [
                [
                    'name' => 'front_end',
                    'title' => 'Frontend',
                    'row_count' => 2,
                ],
                [
                    'name' => 'back_end',
                    'title' => 'Backend',
                    'row_count' => 4,
                ],
            ]
        ],
        [
            'title' => 'OS & Smart Devices',
            'children' => [
                [
                    'title' => 'OS',
                    'name' => 'os',
                    'row_count' => 3,
                ],
                [
                    'title' => 'Smart Devices',
                    'children' => [
                        [
                            'name' => 'pos',
                            'title' => 'Handheld Terminal',
                            'row_count' => 2,
                        ],
                        [
                            'name' => 'screen',
                            'title' => 'Large Screen',
                            'row_count' => 3,
                        ],
                    ]
                ]
            ]
        ]
    ],
    'col' => [
        [
            'title' => 'Personnel',
            'children' => [
                [
                    'name' => 'name',
                    'title' => 'Name',
                    'type' => 'string',
                    'color' => 'red',
                    "width" => -1
                ],
                [
                    'title' => 'Other',
                    'children' => [
                        [
                            'name' => 'age',
                            'title' => 'Age',
                            'type' => 'number',
                        ],
                        [
                            'name' => 'sex',
                            'title' => 'Gender',
                            'type' => 'string',
                            'width' => 8,
                        ],
                        [
                            'title' => 'Hobbies',
                            'children' => [
                                [
                                    'name' => 'love_in',
                                    'title' => 'Indoor',
                                ],
                                [
                                    'title' => 'Outdoor',
                                    'children' => [
                                        [
                                            'name' => 'love_out_land',
                                            'title' => 'Land',
                                        ],
                                        [
                                            'name' => 'love_out_sky',
                                            'title' => 'Sky',
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
            'title' => 'Address',
            'children' => [
                [
                    'name' => 'city',
                    'title' => 'City'
                ],
                [
                    'title' => 'Community',
                    'children' => [
                        [
                            'name' => 'area',
                            'title' => 'Area',
                        ],
                        [
                            'name' => 'building',
                            'title' => 'Building',
                        ]
                    ]
                ]
            ]
        ],
    ],
    'col_' => [
        'name' => 'Name',
        'age' => 'Age',
        'sex' => 'Gender',
        'love_in' => 'Indoor Hobbies',
        'love_out_land' => 'Outdoor Land Hobbies',
        'love_out_sky' => 'Outdoor Sky Hobbies',
        'city' => 'City',
        'area' => 'Area',
        'building' => 'Community',
    ]
];


$d = '{"name":"abc"}';
$s = @unserialize($d);
