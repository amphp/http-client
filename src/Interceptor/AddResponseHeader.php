<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Response;

final class AddResponseHeader extends ModifyResponse
{
    public function __construct(string $headerName, string ...$headerValues)
    {
        parent::__construct(static function (Response $response) use ($headerName, $headerValues) {
            $response->addHeader($headerName, $headerValues);

            return $response;
        });
    }
}
