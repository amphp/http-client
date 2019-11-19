<?php

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use function Amp\call;

final class InterceptedStream implements Stream
{
    use ForbidCloning;
    use ForbidSerialization;

    /** @var Stream */
    private $stream;
    /** @var NetworkInterceptor|null */
    private $interceptor;

    public function __construct(Stream $stream, NetworkInterceptor $interceptor)
    {
        $this->stream = $stream;
        $this->interceptor = $interceptor;
    }

    public function request(Request $request, CancellationToken $cancellation): Promise
    {
        if (!$this->interceptor) {
            throw new \Error(__METHOD__ . ' may only be invoked once per instance. '
                . 'If you need to implement retries or otherwise issue multiple requests, register an ApplicationInterceptor to do so.');
        }

        $interceptor = $this->interceptor;
        $this->interceptor = null;

        return call(function () use ($interceptor, $request, $cancellation) {
            foreach ($request->getEventListeners() as $eventListener) {
                yield $eventListener->startRequest($request);
            }

            return $interceptor->requestViaNetwork($request, $cancellation, $this->stream);
        });
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->stream->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->stream->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->stream->getTlsInfo();
    }
}
