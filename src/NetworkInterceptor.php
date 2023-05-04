<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;
use Amp\Http\Client\Connection\Stream;

/**
 * Allows intercepting an HTTP request after the connection to the remote server has been established.
 */
interface NetworkInterceptor
{
    /**
     * Intercepts an HTTP request after the connection to the remote server has been established.
     *
     * The implementation might modify the request and/or modify the response after `$stream->request(...)` returned.
     *
     * A NetworkInterceptor SHOULD NOT short-circuit and SHOULD delegate to the `$stream` passed as third argument
     * exactly once. The only exception to this is throwing an exception, e.g. because the TLS settings used are
     * unacceptable. If you need short circuits, use an {@see ApplicationInterceptor} instead.
     */
    public function requestViaNetwork(Request $request, Cancellation $cancellation, Stream $stream): Response;
}
