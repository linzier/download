<?php

use WecarSwoole\Util\Mock;
use WecarSwoole\Client\Config\HttpConfig;
use Psr\Http\Message\RequestInterface;

$mock = new Mock();

return [
    /**
     * Supports returning full format (full format must have at least both http_code and body):
     *      [
     *          'http_code' => 200, // http code
     *          'body' => ... // http body, array, string, or any object implementing __toString()
     *          'headers' => [], // http response headers
     *          'activate' => 1, // active flag, 0 means this mock data is disabled and real requests will be made
     *      ]
     */
    'weicar:user.info' => [
        'uid' => $mock->number('100-10000'),
        'name' => $mock->cnName()
    ],
    /**
     * Returns a closure.
     * The closure can perform complex operations such as simulating slow requests or returning HTTP error codes.
     * Return format is the same as above.
     */
    'weicar:coupon.info' => function (HttpConfig $config, RequestInterface $request) use ($mock) {
        return [
            'cid' => $mock->number('1000, 1000000')
        ];
    }
];
