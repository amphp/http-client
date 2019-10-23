<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

interface HttpClient
{
    public function request(Request $request, ?CancellationToken $cancellation = null): Promise;
}
