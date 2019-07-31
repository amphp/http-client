<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;

final class DefaultHeader implements NetworkInterceptor
{
    private $headerName;
    private $headerValue;

    public function __construct(string $headerName, string $headerValue)
    {
        $this->headerName = $headerName;
        $this->headerValue = $headerValue;
    }

    public function interceptNetworkRequest(
        Request $request,
        CancellationToken $cancellationToken,
        Connection $connection
    ): Promise {
        if (!$request->hasHeader($this->headerName)) {
            $request = $request->withHeader($this->headerName, $this->headerValue);
        }

        return $connection->request($request, $cancellationToken);
    }
}
