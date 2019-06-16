<?php

namespace Amp\Http\Client\Driver;

use Amp\Http\Client\ConnectionInfo;
use Amp\Http\Client\Request;

interface HttpDriverFactory
{
    /**
     * @param ConnectionInfo $connectionInfo
     * @param Request        $request
     *
     * @return HttpDriver
     */
    public function selectDriver(ConnectionInfo $connectionInfo, Request $request): HttpDriver;

    /**
     * @return string[] A list of supported application-layer protocols (ALPNs).
     */
    public function getApplicationLayerProtocols(): array;
}
