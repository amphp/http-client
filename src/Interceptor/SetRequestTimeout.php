<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

final class SetRequestTimeout extends ModifyRequest
{
    public function __construct(
        int $tcpConnectTimeout = 10000,
        int $tlsHandshakeTimeout = 10000,
        int $transferTimeout = 10000
    ) {
        parent::__construct(static function (Request $request) use (
            $tcpConnectTimeout,
            $tlsHandshakeTimeout,
            $transferTimeout
        ) {
            $request->setTcpConnectTimeout($tcpConnectTimeout);
            $request->setTlsHandshakeTimeout($tlsHandshakeTimeout);
            $request->setTransferTimeout($transferTimeout);

            return $request;
        });
    }
}
