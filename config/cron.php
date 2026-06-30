<?php

/**
 * Cron job configuration
 * Note: Currently these configurations cannot be fetched from the configuration center via apollo(), because
 * this configuration is read before the service starts and the apollo() function is not yet effective.
 * More importantly, custom processes cannot be restarted via reload, so configuration changes from the
 * configuration center will not take effect immediately.
 */
return [
    // Only execute crontab on these servers. This configuration is required.
    // Supported formats: ['192.168.0.23','172.16.0.31'],
    // Or specify by environment: ['dev' => '192.168.0.23', 'produce' => '120.25.216.158']
    // Note: These two formats are not compatible with each other
    'ip' => ['192.168.0.23'],
    'tasks' => []
];
