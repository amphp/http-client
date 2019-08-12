<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
use Amp\Promise;

interface NetworkInterceptor
{
    public function interceptNetwork(
        Request $request,
        CancellationToken $cancellationToken,
        Stream $stream
    ): Promise;
}
