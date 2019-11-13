<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;

final class AddRequestHeader extends ModifyRequest
{
    use ForbidCloning;
    use ForbidSerialization;

    public function __construct(string $headerName, string ...$headerValues)
    {
        parent::__construct(static function (Request $request) use ($headerName, $headerValues) {
            $request->addHeader($headerName, $headerValues);

            return $request;
        });
    }
}
