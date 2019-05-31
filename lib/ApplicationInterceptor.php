<?php


namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

interface ApplicationInterceptor
{
    public function interceptApplicationRequest(
        Request $request,
        CancellationToken $cancellationToken,
        Client $next
    ): Promise;
}
