<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * Base HTTP client interface for use in {@see ApplicationInterceptor}.
 *
 * Applications and implementations should depend on {@see HttpClient} instead. The intent of this interface is to allow
 * static analysis tools to find interceptors that forget to pass the cancellation token down. This situation is created
 * because of the cancellation token being optional.
 *
 * @see HttpClient
 */
interface DelegateHttpClient
{
    public function request(Request $request, CancellationToken $cancellation): Promise;
}
