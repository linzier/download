<?php

use WecarSwoole\Client\Http\Component\DefaultHttpRequestAssembler;
use WecarSwoole\Client\Http\Component\JsonResponseParser;
use WecarSwoole\Client\Http\Middleware\LogRequestMiddleware;
use WecarSwoole\Client\Http\Middleware\MockRequestMiddleware;

/**
 * External API definitions
 * Supports multiple protocols (typically HTTP, RPC)
 * External API usage format: group_name:apiname
 */
return [
    'config' => [
        // Request protocol
        'protocol' => 'http', // Supported protocols: http, rpc (not yet implemented)
        // HTTP protocol request default configuration
        'http' => [
            // Request parameter assembler
            'request_assembler' => DefaultHttpRequestAssembler::class,
            // Response parameter parser
            'response_parser' => JsonResponseParser::class,
            // Request middlewares, must implement \WecarSwoole\Client\Http\Middleware\IRequestMiddleware interface
            'middlewares' => [
                LogRequestMiddleware::class,
                MockRequestMiddleware::class
            ],
            'throw_exception' => true, // Whether to throw an exception when the response is not 20X
            // HTTPS SSL configuration
            'ssl' => [
                // CA file path
                'cafile' => '',
                // Whether to verify the server certificate
                'ssl_verify_peer' => false,
                // Whether to allow self-signed certificates
                'ssl_allow_self_signed' => true
            ]
        ],
        'default_retry_num' => 2,
    ],
    // API groups
    'weicheche' => include_once __DIR__ . '/weicheche.php'
];
