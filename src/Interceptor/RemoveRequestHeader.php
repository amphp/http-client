<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;

final class RemoveRequestHeader extends ModifyRequest
{
    use ForbidCloning;
    use ForbidSerialization;

    public function __construct(string $headerName)
    {
        parent::__construct(static function (Request $request) use ($headerName) {
            $request->removeHeader($headerName);

            return $request;
        });
    }
}
