<?php

namespace App\Domain;

use App\ErrCode;
use WecarSwoole\Exceptions\Exception;

/**
 * URI
 * 支持空 URI，及 protocol 类型为 PROTO_NONE
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
     * 目前进支持 http，如果有其他协议，请创建子类处理
     */
    protected function setProtocol(string $url)
    {
        if (!$url) {
            $this->protocol = self::PROTO_NONE;
            return;
        }

        if (strpos($url, 'http') !== 0) {
            throw new Exception("暂不支持的协议类型。原始 url：{$url}");
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
            throw new Exception("Url格式不合法", ErrCode::PARAM_VALIDATE_FAIL);
        }

        $this->url = $url;
    }
}
