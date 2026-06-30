<?php

use WecarSwoole\Client\Http\Component\WecarHttpRequestAssembler;
use WecarSwoole\Client\Http\Component\JsonResponseParser;

/**
 * Weicheche internal subsystem API definitions
 */
return [
    'config' => [
        'http' => [
            // Request parameter assembler
            'request_assembler' => WecarHttpRequestAssembler::class,
            // Response parameter parser
            'response_parser' => JsonResponseParser::class,
        ]
    ],
    // API definitions
    'api' => [
        // Do not remove this, used for alert SMS
        'sms.send' => [
            'server' => 'DX',
            'path' => 'v1.0/sms/send',
            'method' => 'POST',
        ],
        'test.sync' => [
            'server' => 'http://localhost:9588',
            'path' => '/v1/download/sync',
            'method' => 'GET'
        ],
    ]
];
