<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Connection;
use Amp\Promise;

interface NetworkInterceptor
{
    public function interceptNetworkRequest(
        Request $request,
        CancellationToken $cancellationToken,
        Connection $connection
    ): Promise;
}
