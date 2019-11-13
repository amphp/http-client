<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Response;

final class SetResponseHeaderIfUnset extends ModifyResponse
{
    public function __construct(string $headerName, string $headerValue, string ...$headerValues)
    {
        \array_unshift($headerValues, $headerValue);

        parent::__construct(static function (Response $response) use ($headerName, $headerValues) {
            if (!$response->hasHeader($headerName)) {
                $response->setHeader($headerName, $headerValues);
            }

            return $response;
        });
    }
}
