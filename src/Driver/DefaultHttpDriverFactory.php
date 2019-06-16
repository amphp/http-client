<?php

namespace Amp\Http\Client\Driver;

use Amp\Http\Client\ConnectionInfo;
use Amp\Http\Client\Request;

final class DefaultHttpDriverFactory implements HttpDriverFactory
{
    public function selectDriver(ConnectionInfo $connectionInfo, Request $request): HttpDriver
    {
        return new Http1Driver;
    }

    public function getApplicationLayerProtocols(): array
    {
        return ['http/1.1'];
    }
}
