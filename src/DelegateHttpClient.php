<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Promise;

/**
 * Base HTTP client interface for use in {@see ApplicationInterceptor}.
 *
 * Applications and implementations should depend on {@see HttpClient} instead. The intent of this interface is to
 * allow static analysis tools to find interceptors that forget to pass the cancellation token down. This situation is
 * created because of the cancellation token being optional.
 *
 * Before executing or delegating the request, any client implementation must call {@see EventListener::startRequest()}
 * on all event listeners registered on the given request in the order defined by {@see Request::getEventListeners()}.
 * Before calling the next listener, the promise returned from the previous one must resolve successfully.
 *
 * @see HttpClient
 */
interface DelegateHttpClient
{
    /**
     * Request a specific resource from an HTTP server.
     *
     * @param Request           $request
     * @param CancellationToken $cancellation
     *
     * @return Promise<Response>
     */
    public function request(Request $request, CancellationToken $cancellation): Promise;
}
