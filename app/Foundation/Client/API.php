<?php

namespace App\Foundation\Client;

use App\ErrCode;
use Psr\Log\LoggerInterface;
use WecarSwoole\Client\API as BaseAPI;
use WecarSwoole\Client\Response;
use WecarSwoole\Client\Response\JsonArrayResponse;
use WecarSwoole\Exceptions\Exception;
use Swoole\Coroutine as Co;
use WecarSwoole\Client\Http\Component\JsonResponseParser;
use WecarSwoole\Client\Http\Component\WecarWithNoZipHttpRequestAssembler;
use WecarSwoole\Container;

class API
{
    private const MAX_RETRY_NUM = 3;

    private $retryNum = 0;
    private $lastErrNo = 0;
    private $lastErrMsg = '';
    private $url;
    private $method;

    public function __construct(string $url = '', string $method = 'GET')
    {
        $this->url = $url;
        $this->method = $method;
    }

    public function setUrl(string $url)
    {
        $this->url = $url;
    }

    /**
     * On failure, retries up to 3 times with increasing intervals between attempts.
     * Note: retries are blocking for the current coroutine and will trigger coroutine switching.
     * @return array
     */
    public function invoke(array $params): array
    {
        $this->retryNum = 0;

        $result = $this->retryCall($params);
        if (!$result || $result->getStatus() >= 300) {
            throw new Exception("URL request failed: {$this->url}. errno:{$this->lastErrNo}, errmsg:{$this->lastErrMsg}", ErrCode::FETCH_SOURCE_FAILED);
        }

        return $result->getBody() ?: [];
    }

    private function retryCall(array $params): Response
    {
        $result = null;
        $paramsStr = http_build_query($params);
        while ($this->retryNum++ < self::MAX_RETRY_NUM) {
            try {
                $result = BaseAPI::simpleInvoke(
                    $this->url,
                    $this->method,
                    $params,
                    '_',
                    [
                        'timeout' => 10,
                        'request_assembler' => WecarWithNoZipHttpRequestAssembler::class,
                        'response_parser' => JsonResponseParser::class,
                    ]
                );

                // Retry if HTTP status code is not 20X
                if ($result && $result->getStatus() >= 200 && $result->getStatus() < 300) {
                    $this->lastErrNo = 0;
                    $this->lastErrMsg = '';
                    return $result;
                }
            } catch (\Exception $e) {
                $result = new JsonArrayResponse([], $e->getCode(), $e->getMessage());
            }

            $this->lastErrNo = $result->getStatus();
            $this->lastErrMsg = $result->getMessage();

            Co::sleep($this->calcIntervalTime());
            Container::get(LoggerInterface::class)->warning("Retry #{$this->retryNum} for {$this->url}, params:{$paramsStr}, reason: {$this->lastErrMsg}, http code:{$this->lastErrNo}");
        }

        return $result === null ? new JsonArrayResponse([], 500, self::MAX_RETRY_NUM . " retries failed: {$this->url}, params:{$paramsStr}") : $result;
    }

    private function calcIntervalTime(): int
    {
        return pow($this->retryNum, 3) * 5;
    }
}
