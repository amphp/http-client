<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
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

    public function interceptNetwork(
        Request $request,
        CancellationToken $cancellationToken,
        Stream $stream
    ): Promise {
        if (!$request->hasHeader($this->headerName)) {
            $request->setHeader($this->headerName, $this->headerValue);
        }

        return $stream->request($request, $cancellationToken);
    }
}
