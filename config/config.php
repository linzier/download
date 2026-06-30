<?php

use function WecarSwoole\Config\apollo;
use WecarSwoole\Util\File;

$baseConfig = [
    'app_name' => 'Download Center',
    // Application identifier
    'app_flag' => 'XZ',
    'app_id' => 10049,
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
        'download' => [
            // Read replicas configured as a 2D array to support multiple read replicas
            'read' => [
                [
                    'host' => apollo('FW.mysql.download_center.rw', 'download_center.host'),
                    'port' => apollo('FW.mysql.download_center.rw', 'download_center.port'),
                    'user' => apollo('FW.mysql.download_center.rw', 'download_center.username'),
                    'password' => apollo('FW.mysql.download_center.rw', 'download_center.password'),
                    'database' => apollo('FW.mysql.download_center.rw', 'download_center.dbname'),
                    'charset' => apollo('FW.mysql.download_center.rw', 'download_center.charset'),
                ]
            ],
            // Only one write replica supported
            'write' => [
                'host' => apollo('FW.mysql.download_center.rw', 'download_center.host'),
                'port' => apollo('FW.mysql.download_center.rw', 'download_center.port'),
                'user' => apollo('FW.mysql.download_center.rw', 'download_center.username'),
                'password' => apollo('FW.mysql.download_center.rw', 'download_center.password'),
                'database' => apollo('FW.mysql.download_center.rw', 'download_center.dbname'),
                'charset' => apollo('FW.mysql.download_center.rw', 'download_center.charset'),
            ],
            // Connection pool configuration
            'pool' => [
                'size' => apollo('application', 'mysql.weicheche.pool_size') ?: 15
            ]
        ],
    ],
    'redis' => [
        'main' => [
            'host' => apollo('application', 'redis_main_host'),
            'port' => apollo('application', 'redis_main_port'),
            'auth' => apollo('application', 'redis_main_auth'),
            'database' => apollo('application', 'redis_main_database') ?? 0,
            // Connection pool configuration
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.main.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.main.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.main.idle_time') ?? 300,
            ],
        ],
        'cache' => [
            'host' => apollo('application', 'redis_main_host'),
            'port' => apollo('application', 'redis_main_port'),
            'auth' => apollo('application', 'redis_main_auth'),
            'database' => apollo('application', 'redis_main_database') ?? 0,
            // Connection pool configuration
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.cache.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.cache.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.cache.idle_time') ?? 300,
            ]
        ],
        'queue' => [
            'host' => apollo('application', 'redis_main_host'),
            'port' => apollo('application', 'redis_main_port'),
            'auth' => apollo('application', 'redis_main_auth'),
            'database' => apollo('application', 'redis_main_database') ?? 0,
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
        // Available drivers: redis, file, array, null (null is typically used to disable caching during testing)
        'driver' => apollo('application', 'cache.driver') ?: 'file',
        'prefix' => 'download',
        'expire' => 3600, // Default cache expiration time in seconds
        'redis' => 'cache', // Which Redis config to use when driver = redis
        'dir' => File::join(EASYSWOOLE_ROOT, 'storage/cache'), // Cache directory when driver = file
    ],
    // Minimum log level: debug, info, warning, error, critical, off
    'log_level' => apollo('application', 'log_level') ?: 'info',
    'base_url' => apollo('application', 'base_url'),
    // Whether to log API invocations
    'api_invoke_log' => apollo('application', 'api_invoke_log') ?: 'on',
    // Task queue name
    'task_queue' => 'download-task',
    // Maximum number of concurrently executing tasks per process
    'task_concurrent_limit' => apollo('application', 'task_concurrent_limit') ?: 20,
    // Base path for local temporary file storage
    'local_file_base_dir' => File::join(EASYSWOOLE_ROOT, 'storage/data'),
    // Maximum size of a single Excel file (source file size), in bytes
    'excel_max_size' => apollo('application', 'excel_max_size') ?: 50 * 1024 * 1024,
    // Maximum number of rows per Excel file
    'excel_max_count' => apollo('application', 'excel_max_count') ?: 10000,
    // File compression threshold, in bytes
    'zip_threshold' => apollo('application', 'zip_threshold') ?: 200 * 1024,
    // File compression type, currently only zip is supported
    'zip_type' => apollo('application', 'zip_type') ?: 'zip',
    // Alibaba Cloud OSS service access key
    'oss_access_key' => apollo('application', 'oss_access_key'),
    // Alibaba Cloud OSS service secret
    'oss_access_secret' => apollo('application', 'oss_access_secret'),
    // Alibaba Cloud OSS service endpoint (regional data center domain)
    'oss_endpoint' => apollo('application', 'oss_endpoint'),
    // Alibaba Cloud OSS bucket (created in the Alibaba Cloud OSS console)
    'oss_bucket' => apollo('application', 'oss_bucket'),
    // Temporary download URL (frontend download, no token verification required)
    'tmp_download_url' => apollo('application', 'tmp_download_url') ?: '/v1/download',
    // Backend download URL (token verification required)
    'backend_download_url' => apollo('application', 'backend_download_url') ?: '/v1/download/async',
    // Maximum number of downloads allowed per task within 10 minutes
    'download_10m_limit' => apollo('application', 'download_10m_limit') ?: 5,
    // Task download expiration period (downloads not allowed after this period), in seconds
    'download_expire' => apollo('application', 'download_expire') ?: 86400 * 7,
    // Number of tasks a process handles before it restarts
    'task_max_process' => apollo('application', 'task_max_process') ?: 1000,
    // Task processing time limit (tasks still "in progress" beyond this limit will be re-queued). This parameter can also be specified at task submission time. This value is used in the custom process; changes require a manual service restart (cannot be reloaded)
    'max_exec_time' => apollo('application', 'max_exec_time') ?: 3600,
    // Master server IP. The defender process runs on the master server
    'master_server' => apollo('application', 'master_server'),
    // Whether to perform token verification on related APIs (APIs extending ApiRoute). Verification is enabled by default; this parameter is mainly used to temporarily disable verification for testing
    'auth_request' => apollo('application', 'auth_request') ?? 1,
];

return array_merge(
    $baseConfig,
    ['logger' => include_once __DIR__ . '/logger.php'],
    ['api' => require_once __DIR__ . '/api/api.php'],
    ['subscriber' => require_once __DIR__ . '/subscriber/subscriber.php']
);
