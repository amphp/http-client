<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Socket\TlsInfo;

/**
 * Allows listening to more fine granular events than interceptors are able to achieve.
 *
 * All event listener methods might be called multiple times for a single request. The implementing listener is
 * responsible to detect another call, e.g. via attributes in the request.
 */
interface EventListener
{
    /**
     * Called when the request starts being processed by {@see DelegateHttpClient::request()}.
     */
    public function requestStart(Request $request): void;

    /**
     * Called when the request failed due to an exception.
     */
    public function requestFailed(Request $request, HttpException $exception): void;

    /**
     * Called when the request completed with a complete response.
     */
    public function requestEnd(Request $request, Response $response): void;

    /**
     * Called before an application interceptor is invoked.
     */
    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void;

    /**
     * Called after an application interceptor is returned.
     */
    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void;

    /**
     * Called before a network interceptor is invoked.
     */
    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void;

    /**
     * Called after a network interceptor is returned.
     */
    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void;

    /**
     * Called before a new connection is initiated.
     */
    public function connectStart(Request $request): void;

    /**
     * Called when a new connection is established.
     */
    public function connectEnd(Request $request, Connection $connection): void;

    /**
     * Called before TLS negotiation is started (HTTPS-only).
     */
    public function tlsHandshakeStart(Request $request): void;

    /**
     * Called when TLS negotiation completed (HTTPS-only).
     */
    public function tlsHandshakeEnd(Request $request, TlsInfo $tlsInfo): void;

    /**
     * Called before the request headers are sent.
     */
    public function requestHeaderStart(Request $request, Stream $stream): void;

    /**
     * Called after the request headers have been sent.
     */
    public function requestHeaderEnd(Request $request, Stream $stream): void;

    /**
     * Called before the request body is sent.
     */
    public function requestBodyStart(Request $request, Stream $stream): void;

    /**
     * Called when a new chunk of the request body has been sent.
     */
    public function requestBodyProgress(Request $request, Stream $stream): void;

    /**
     * Called after the request body has been completely sent.
     */
    public function requestBodyEnd(Request $request, Stream $stream): void;

    /**
     * Called after the first chunk of the response headers has been received.
     */
    public function responseHeaderStart(Request $request, Stream $stream): void;

    /**
     * Called after the response headers have been completely received.
     */
    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void;

    /**
     * Called after the first chunk of the response body has been received.
     */
    public function responseBodyStart(Request $request, Stream $stream, Response $response): void;

    /**
     * Called after a new chunk of the response body has been received.
     */
    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void;

    /**
     * Called after the response body has been completely received.
     */
    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void;
}
