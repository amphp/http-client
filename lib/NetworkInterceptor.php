<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

interface NetworkInterceptor
{
    public function interceptNetworkRequest(
        Request $request,
        CancellationToken $cancellationToken,
        ConnectionInfo $connectionInfo,
        Client $next
    ): Promise;
}
