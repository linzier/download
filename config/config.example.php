<?php

use function WecarSwoole\Config\apollo;
use WecarSwoole\Util\File;

$baseConfig = [
    'app_name' => 'User System',
    // Application identifier
    'app_flag' => 'YH',
    'app_id' => 10017,
    'request_id_key' => 'wcc-request-id',
    'server' => [
        'modules' => apollo('fw.modules'),
        'app_ids' => apollo('fw.appids'),
    ],
    // Mailer. Multiple mailers can be configured
    'mailer' => [
        'default' => [
            'host' => apollo('fw.mail', 'mail.host'),
            'username' => apollo('fw.mail', 'mail.username'),
            'password' => apollo('fw.mail', 'mail.password'),
            'port' => apollo('fw.mail', 'mail.port') ?: 25,
            'encryption' => apollo('fw.mail', 'mail.encryption') ?: 'ssl',
        ]
    ],
    // Concurrent lock configuration
    'concurrent_locker' => [
        'onoff' => apollo('application', 'concurrent_locker.onoff') ?: 'off',
        'redis' => apollo('application', 'concurrent_locker.redis') ?: 'main',
    ],
    // Request log configuration. Disabled by default; set to 'on' to enable
    'request_log' => [
        'onoff' => apollo('application', 'request_log.onoff') ?: 'off',
        // Which request methods to log
        'methods' => explode(',', apollo('application', 'request_log.methods'))
    ],
    /**
     * It is recommended to use the database name as the key
     * If read-write separation is not needed, there is no need to split into read/write -- just write the config directly
     */
    'mysql' => [
        'weicheche' => [
            // Read replicas configured as a 2D array to support multiple read replicas
            'read' => [
                [
                    'host' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.host'),
                    'port' => apollo('fw.mysql.weicheche.ro', 'weicheche.port'),
                    'user' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.username'),
                    'password' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.password'),
                    'database' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.dbname'),
                    'charset' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.charset'),
                ]
            ],
            // Only one write replica supported
            'write' => [
                'host' => apollo('fw.mysql.weicheche.rw', 'weicheche.host'),
                'port' => apollo('fw.mysql.weicheche.rw', 'weicheche.port'),
                'user' => apollo('fw.mysql.weicheche.rw', 'weicheche.username'),
                'password' => apollo('fw.mysql.weicheche.rw', 'weicheche.password'),
                'database' => apollo('fw.mysql.weicheche.rw', 'weicheche.dbname'),
                'charset' => apollo('fw.mysql.weicheche.rw', 'weicheche.charset'),
            ],
            // Connection pool configuration
            'pool' => [
                'size' => apollo('application', 'mysql.weicheche.pool_size') ?: 15
            ]
        ],
    ],
    'redis' => [
        'main' => [
            'host' => apollo('fw.redis.01', 'redis.host'),
            'port' => apollo('fw.redis.01', 'redis.port'),
            'auth' => apollo('fw.redis.01', 'redis.auth'),
            'database' => apollo('fw.redis.01', 'redis.database') ?? 0,
            // Connection pool configuration
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.main.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.main.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.main.idle_time') ?? 300,
            ],
        ],
        'cache' => [
            'host' => apollo('fw.redis.01', 'redis.host'),
            'port' => apollo('fw.redis.01', 'redis.port'),
            'auth' => apollo('fw.redis.01', 'redis.auth'),
            'database' => apollo('fw.redis.01', 'redis.database') ?? 0,
            // Connection pool configuration
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.cache.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.cache.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.cache.idle_time') ?? 300,
            ]
        ],
    ],
    // Cache configuration
    'cache' => [
        'driver' => apollo('application', 'cache.driver'), // Available drivers: redis, file, array, null (null is typically used to disable caching during testing)
        'prefix' => 'usercenter',
        'expire' => 3600, // Default cache expiration time in seconds
        'redis' => 'cache', // Which Redis config to use when driver = redis
        'dir' => File::join(EASYSWOOLE_ROOT, 'storage/cache'), // Cache directory when driver = file
    ],
    // Minimum log level: debug, info, warning, error, critical, off
    'log_level' => apollo('application', 'log_level') ?: 'info',
    'base_url' => apollo('application', 'base_url'),
];

return array_merge(
    $baseConfig,
    ['logger' => include_once __DIR__ . '/logger.php'],
    ['api' => require_once __DIR__ . '/api/api.php'],
    ['subscriber' => require_once __DIR__ . '/subscriber/subscriber.php']
);
