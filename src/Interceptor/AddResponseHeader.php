<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Response;

final class AddResponseHeader extends ModifyResponse
{
    use ForbidCloning;
    use ForbidSerialization;

    public function __construct(string $headerName, string ...$headerValues)
    {
        parent::__construct(static function (Response $response) use ($headerName, $headerValues) {
            $response->addHeader($headerName, $headerValues);

            return $response;
        });
    }
}
