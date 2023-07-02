<?php

namespace Amp\Http\Client\Internal;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
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
        return self::get()->requestPhase[$request] ?? Phase::Unprocessed;
    }

    private \WeakMap $requestPhase;

    public function __construct()
    {
        $this->requestPhase = new \WeakMap();
    }

    private function invoke(Request $request, \Closure $closure): void
    {
        foreach ($request->getEventListeners() as $eventListener) {
            $closure($eventListener, $request);
        }
    }

    public function requestStart(Request $request): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::Unprocessed) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to Blocked');
        }

        $this->requestPhase[$request] = Phase::Blocked;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestStart($request));
    }

    public function requestFailed(Request $request, HttpException $exception): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase === Phase::Complete || $previousPhase === Phase::Failed) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to Failed');
        }

        $this->requestPhase[$request] = Phase::Failed;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestFailed($request, $exception));
    }

    public function requestEnd(Request $request, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::Blocked && $previousPhase !== Phase::ResponseHeaders && $previousPhase !== Phase::ResponseBody) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to Complete');
        }

        $this->requestPhase[$request] = Phase::Complete;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestEnd($request, $response));
    }

    public function connectStart(Request $request): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::Blocked) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to Connect');
        }

        $this->requestPhase[$request] = Phase::Connect;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->connectStart($request));
    }

    public function connectEnd(Request $request, Connection $connection): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::Connect) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to Blocked');
        }

        $this->requestPhase[$request] = Phase::Blocked;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->connectEnd($request, $connection));
    }

    public function tlsHandshakeStart(Request $request): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::Connect) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to TlsHandshake');
        }

        $this->requestPhase[$request] = Phase::TlsHandshake;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->tlsHandshakeStart($request));
    }

    public function tlsHandshakeEnd(Request $request, TlsInfo $tlsInfo): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::TlsHandshake) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to Connect');
        }

        $this->requestPhase[$request] = Phase::Connect;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->tlsHandshakeEnd($request, $tlsInfo));
    }

    public function requestHeaderStart(Request $request, Stream $stream): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::Blocked) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to RequestHeaders');
        }

        $this->requestPhase[$request] = Phase::RequestHeaders;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestHeaderStart($request, $stream));
    }

    public function requestHeaderEnd(Request $request, Stream $stream): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::RequestHeaders) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestHeaderEnd($request, $stream));
    }

    public function requestBodyStart(Request $request, Stream $stream): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::RequestHeaders) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to RequestBody');
        }

        $this->requestPhase[$request] = Phase::RequestBody;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestBodyStart($request, $stream));
    }

    public function requestBodyProgress(Request $request, Stream $stream): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::RequestBody) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestBodyProgress($request, $stream));
    }

    public function requestBodyEnd(Request $request, Stream $stream): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::RequestBody) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to ServerProcessing');
        }

        $this->requestPhase[$request] = Phase::ServerProcessing;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->requestBodyEnd($request, $stream));
    }

    public function responseHeaderStart(Request $request, Stream $stream): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::ResponseHeaders && $previousPhase !== Phase::ServerProcessing) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to ResponseHeaders');
        }

        $this->requestPhase[$request] = Phase::ResponseHeaders;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseHeaderStart($request, $stream));
    }

    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::ResponseHeaders) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseHeaderEnd($request, $stream, $response));
    }

    public function responseBodyStart(Request $request, Stream $stream, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::ResponseHeaders) {
            throw new \Error('Invalid request phase transition from ' . $previousPhase->name . ' to ResponseBody');
        }

        $this->requestPhase[$request] = Phase::ResponseBody;

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseBodyStart($request, $stream, $response));
    }

    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::ResponseBody) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseBodyProgress($request, $stream, $response));
    }

    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase !== Phase::ResponseBody) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->responseBodyEnd($request, $stream, $response));
    }

    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase === Phase::Unprocessed) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->applicationInterceptorStart($request, $interceptor));
    }

    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase === Phase::Unprocessed) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->applicationInterceptorEnd($request, $interceptor, $response));
    }

    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase === Phase::Unprocessed) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->networkInterceptorStart($request, $interceptor));
    }

    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void
    {
        $previousPhase = self::getPhase($request);
        if ($previousPhase === Phase::Unprocessed) {
            throw new \Error('Invalid request phase: ' . $previousPhase->name);
        }

        $this->invoke($request, fn (EventListener $eventListener) => $eventListener->networkInterceptorEnd($request, $interceptor, $response));
    }
}
