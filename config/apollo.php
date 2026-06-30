<?php

/**
 * Apollo Configuration Center
 */
return [
    'app_id' => 1008633,
    'server' => [
        'dev' => 'http://192.168.85.242:8080',
        'test' => 'http://test.configserver.zhyz.cn:8080',
        'preview' => 'http://preview.configserver.zhihuiyouzhan.cn:8080',
        'produce' => 'http://production.configserver.zhihuiyouzhan.cn:8080',
    ],
    // Namespaces to watch
    'namespaces' => [
        'application',
        'fw.appids',
        'fw.modules',
        'fw.mail',
        'FW.mysql.download_center.rw',
    ]
];
