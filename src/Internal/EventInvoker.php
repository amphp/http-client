<?php

namespace Amp\Http\Client\Internal;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Phase;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Socket\TlsException;
use Amp\Socket\TlsInfo;

/** @internal */
final class EventInvoker implements EventListener
{
    private static self $instance;

    public static function get(): self
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        return self::$instance ??= new self;
    }

    public static function getPhase(Request $request): Phase
    {
        return self::get()->requestState[$request] ?? Phase::Unprocessed;
    }

    private \WeakMap $requestState;

    public function __construct()
    {
        $this->requestState = new \WeakMap();
    }

    private function invoke(Request $request, \Closure $closure): void
    {
        foreach ($request->getEventListeners() as $listener) {
            $closure($listener, $request);
        }
    }

    public function requestStart(Request $request): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestStart($request));
    }

    public function requestFailed(Request $request, HttpException $exception): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestFailed($request, $exception));
    }

    public function requestEnd(Request $request, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestEnd($request, $response));
    }

    public function connectStart(Request $request): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->connectStart($request));
    }

    public function connectFailed(Request $request, SocketException $exception): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->connectFailed($request, $exception));
    }

    public function connectEnd(Request $request, Connection $connection): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->connectEnd($request, $connection));
    }

    public function tlsHandshakeStart(Request $request): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->tlsHandshakeStart($request));
    }

    public function tlsHandshakeFailed(Request $request, Connection $connection, TlsException $exception): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->tlsHandshakeFailed($request, $connection, $exception));
    }

    public function tlsHandshakeEnd(Request $request, TlsInfo $tlsInfo): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->tlsHandshakeEnd($request, $tlsInfo));
    }

    public function requestHeaderStart(Request $request, Stream $stream): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestHeaderStart($request, $stream));
    }

    public function requestHeaderEnd(Request $request, Stream $stream): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestHeaderEnd($request, $stream));
    }

    public function requestBodyStart(Request $request, Stream $stream): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestBodyStart($request, $stream));
    }

    public function requestBodyProgress(Request $request, Stream $stream): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestBodyProgress($request, $stream));
    }

    public function requestBodyEnd(Request $request, Stream $stream): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestBodyEnd($request, $stream));
    }

    public function responseHeaderStart(Request $request, Stream $stream): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseHeaderStart($request, $stream));
    }

    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseHeaderEnd($request, $stream, $response));
    }

    public function responseBodyStart(Request $request, Stream $stream, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseBodyStart($request, $stream, $response));
    }

    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseBodyProgress($request, $stream, $response));
    }

    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseBodyEnd($request, $stream, $response));
    }

    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->applicationInterceptorStart($request, $interceptor));
    }

    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->applicationInterceptorEnd($request, $interceptor, $response));
    }

    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->networkInterceptorStart($request, $interceptor));
    }

    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void
    {
        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->networkInterceptorEnd($request, $interceptor, $response));
    }
}
