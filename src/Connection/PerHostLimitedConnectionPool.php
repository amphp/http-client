<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\Request;

final class PerHostLimitedConnectionPool extends LimitedConnectionPool
{
    protected function getKey(Request $request): string
    {
        return $request->getUri()->getHost();
    }
}
