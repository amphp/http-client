<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

interface ApplicationInterceptor
{
    public function interceptApplication(
        Request $request,
        CancellationToken $cancellation,
        Client $client
    ): Promise;
}
