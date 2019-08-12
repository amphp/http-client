<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Response;

final class SetResponseHeader extends ModifyResponse
{
    public function __construct(string $headerName, string $headerValue, string ...$headerValues)
    {
        \array_unshift($headerValues, $headerValue);

        parent::__construct(static function (Response $response) use ($headerName, $headerValues) {
            $response->setHeader($headerName, $headerValues);

            return $response;
        });
    }
}
