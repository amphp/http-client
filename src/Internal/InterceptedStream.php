<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Failure;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

/** @internal */
final class InterceptedStream implements Stream
{
    /** @var Stream */
    private $stream;
    /** @var NetworkInterceptor[] */
    private $interceptors;
    /** @var bool */
    private $called = false;

    public function __construct(Stream $stream, NetworkInterceptor... $interceptors)
    {
        $this->stream = $stream;
        $this->interceptors = $interceptors;
    }

    public function request(Request $request, CancellationToken $cancellation): Promise
    {
        if (!$this->interceptors) {
            if ($this->called) {
                return new Failure(new HttpException(__METHOD__ . ' may only be invoked once per instance. '
                    . 'If you need to implement retries or otherwise issue multiple requests, register an ApplicationInterceptor to do so.'));
            }

            $this->called = true;

            return $this->stream->request($request, $cancellation);
        }

        $interceptor = \array_shift($this->interceptors);

        return $interceptor->interceptNetworkRequest($request, $cancellation, $this);
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
