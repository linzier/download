<?php

namespace App\Domain;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * URI
 * Supports empty URIs and the PROTO_NONE protocol type
 */
class URI
{
    public const PROTO_NONE = 0;
    public const PROTO_HTTP = 1;

    protected $protocol;
    protected $url;

    public function __construct(string $url)
    {
        $this->setProtocol($url);
        $this->setUrl($url);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function protocol(): int
    {
        return $this->protocol;
    }

    /**
     * Currently only HTTP is supported; for other protocols, create a subclass
     */
    protected function setProtocol(string $url)
    {
        if (!$url) {
            $this->protocol = self::PROTO_NONE;
            return;
        }

        if (strpos($url, 'http') !== 0) {
            throw new Exception("Unsupported protocol type. Original URL: {$url}");
        }

        $this->protocol = self::PROTO_HTTP;
    }

    protected function setUrl(string $url)
    {
        if (!$url) {
            $this->url = '';
            return;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL format", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->url = $url;
    }
}
