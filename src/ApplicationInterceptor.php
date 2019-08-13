<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

interface ApplicationInterceptor
{
    public function request(
        Request $request,
        CancellationToken $cancellation,
        Client $client
    ): Promise;
}
