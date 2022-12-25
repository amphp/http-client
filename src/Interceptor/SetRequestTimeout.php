<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

final class SetRequestTimeout extends ModifyRequest
{
    public function __construct(
        float $tcpConnectTimeout = 10,
        float $tlsHandshakeTimeout = 10,
        float $transferTimeout = 10,
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
