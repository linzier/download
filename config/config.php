<?php

use function WecarSwoole\Config\apollo;
use WecarSwoole\Util\File;

$baseConfig = [
    'app_name' => '下载中心',
    // 应用标识
    'app_flag' => 'XZ',
    'app_id' => 10049,
    'request_id_key' => 'wcc-request-id',
    'server' => [
        'modules' => apollo('fw.modules'),
        'app_ids' => apollo('fw.appids'),
    ],
    // 邮件。可以配多个
    'mailer' => [
        'default' => [
            'host' => apollo('fw.mail', 'mail.host'),
            'username' => apollo('fw.mail', 'mail.username'),
            'password' => apollo('fw.mail', 'mail.password'),
            'port' => apollo('fw.mail', 'mail.port') ?: 25,
            'encryption' => apollo('fw.mail', 'mail.encryption') ?: 'ssl',
        ]
    ],
    // 并发锁配置
    'concurrent_locker' => [
        'onoff' => apollo('application', 'concurrent_locker.onoff') ?: 'off',
        'redis' => apollo('application', 'concurrent_locker.redis') ?: 'main',
    ],
    // 请求日志配置。默认是关闭的，如果项目需要开启，则自行修改为 on
    'request_log' => [
        'onoff' => apollo('application', 'request_log.onoff') ?: 'off',
        // 记录哪些请求类型的日志
        'methods' => explode(',', apollo('application', 'request_log.methods'))
    ],
    /**
     * 数据库配置建议以数据库名作为 key
     * 如果没有读写分离，则可不分 read, write，直接在里面写配置信息
     */
    'mysql' => [
        'download' => [
            // 读库使用二维数组配置，以支持多个读库
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
            // 仅支持一个写库
            'write' => [
                'host' => apollo('FW.mysql.download_center.rw', 'download_center.host'),
                'port' => apollo('FW.mysql.download_center.rw', 'download_center.port'),
                'user' => apollo('FW.mysql.download_center.rw', 'download_center.username'),
                'password' => apollo('FW.mysql.download_center.rw', 'download_center.password'),
                'database' => apollo('FW.mysql.download_center.rw', 'download_center.dbname'),
                'charset' => apollo('FW.mysql.download_center.rw', 'download_center.charset'),
            ],
            // 连接池配置
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
            // 连接池配置
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
            // 连接池配置
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
            // 连接池配置
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.cache.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.cache.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.cache.idle_time') ?? 300,
            ]
        ],
    ],
    // 缓存配置
    'cache' => [
        // 可用：redis、file、array、null(一般测试时用来禁用缓存)
        'driver' => apollo('application', 'cache.driver') ?: 'file',
        'prefix' => 'download',
        'expire' => 3600, // 缓存默认过期时间，单位秒
        'redis' => 'cache', // 当 driver = redis 时，使用哪个 redis 配置
        'dir' => File::join(EASYSWOOLE_ROOT, 'storage/cache'), // 当 driver = file 时，缓存存放目录
    ],
    // 最低记录级别：debug, info, warning, error, critical, off
    'log_level' => apollo('application', 'log_level') ?: 'info',
    'base_url' => apollo('application', 'base_url'),
    // 是否记录 api 调用日志
    'api_invoke_log' => apollo('application', 'api_invoke_log') ?: 'on',
    // 任务队列名称
    'task_queue' => 'download-task',
    // 每个进程并发执行的任务数最大值
    'task_concurrent_limit' => apollo('application', 'task_concurrent_limit') ?: 20,
    // 本地临时文件存储基路径
    'local_file_base_dir' => File::join(EASYSWOOLE_ROOT, 'storage/data'),
    // 单个 excel 文件最大尺寸（以源文件记），单位字节
    'excel_max_size' => apollo('application', 'excel_max_size') ?: 50 * 1024 * 1024,
    // 单个 excel 最大行数
    'excel_max_count' => apollo('application', 'excel_max_count') ?: 10000,
    // 文件压缩阈值，单位字节
    'zip_threshold' => apollo('application', 'zip_threshold') ?: 200 * 1024,
    // 文件压缩类型，目前仅支持 zip 压缩
    'zip_type' => apollo('application', 'zip_type') ?: 'zip',
    // 阿里云 OSS 服务 key
    'oss_access_key' => apollo('application', 'oss_access_key'),
    // 阿里云 OSS 服务 secret
    'oss_access_secret' => apollo('application', 'oss_access_secret'),
    // 阿里云 OSS 服务 endpoint (区域数据中心域名)
    'oss_endpoint' => apollo('application', 'oss_endpoint'),
    // 阿里云 OSS 服务 bucket（在阿里云 OSS 管理后台创建的）
    'oss_bucket' => apollo('application', 'oss_bucket'),
    // 临时下载 url（前端下载，无需 token 验证）
    'tmp_download_url' => apollo('application', 'tmp_download_url') ?: '/v1/download',
    // 后端下载 url（需要 token 验证）
    'backend_download_url' => apollo('application', 'backend_download_url') ?: '/v1/download/async',
    // 同一个任务 10 分钟内允许下载的次数
    'download_10m_limit' => apollo('application', 'download_10m_limit') ?: 5,
    // 任务下载有效期（超过时间将不能下载），单位秒
    'download_expire' => apollo('application', 'download_expire') ?: 86400 * 7,
    // 进程处理完多少任务后将重启
    'task_max_process' => apollo('application', 'task_max_process') ?: 1000,
    // 任务处理时限（超过该时限还在“处理中”的任务将重新入列），任务投递时也可以指定该参数。该值在自定义进程中使用的，修改后需要人工重启服务（无法reload）
    'max_exec_time' => apollo('application', 'max_exec_time') ?: 3600,
    // 主服务器 ip，主服务器上会执行守卫程序
    'master_server' => apollo('application', 'master_server'),
    // 是否对相关接口进行 token 校验（继承 ApiRoute 的接口）。默认需要验证，此参数主要用来临时取消验证进行测试
    'auth_request' => apollo('application', 'auth_request') ?? 1,
];

return array_merge(
    $baseConfig,
    ['logger' => include_once __DIR__ . '/logger.php'],
    ['api' => require_once __DIR__ . '/api/api.php'],
    ['subscriber' => require_once __DIR__ . '/subscriber/subscriber.php']
);
