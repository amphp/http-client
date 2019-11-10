<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\HarAttributes;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;

final class RecordServerIp implements NetworkInterceptor
{
    public function requestViaNetwork(Request $request, CancellationToken $cancellation, Stream $stream): Promise
    {
        $host = $stream->getRemoteAddress()->getHost();
        if (\strrpos($host, ':')) {
            $host = '[' . $host . ']';
        }

        $request->setAttribute(HarAttributes::SERVER_IP_ADDRESS, $host);

        return $stream->request($request, $cancellation);
    }
}
