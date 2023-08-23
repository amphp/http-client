<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use function Amp\Http\Client\events;
use function Amp\Http\Client\processRequest;

final class InterceptedStream implements Stream
{
    use ForbidCloning;
    use ForbidSerialization;

    private static \WeakMap $requestInterceptors;

    private Stream $stream;

    private ?NetworkInterceptor $interceptor;

    public function __construct(Stream $stream, NetworkInterceptor $interceptor)
    {
        $this->stream = $stream;
        $this->interceptor = $interceptor;
    }

    /**
     * @throws HttpException
     */
    public function request(Request $request, Cancellation $cancellation): Response
    {
        return processRequest($request, [], function () use ($request, $cancellation): Response {
            if (!$this->interceptor) {
                throw new \Error(__METHOD__ . ' may only be invoked once per instance. '
                    . 'If you need to implement retries or otherwise issue multiple requests, register an ApplicationInterceptor to do so.');
            }

            $interceptor = $this->interceptor;
            $this->interceptor = null;

            /** @psalm-suppress RedundantPropertyInitializationCheck */
            self::$requestInterceptors ??= new \WeakMap();
            $requestInterceptors = self::$requestInterceptors[$request] ?? [];
            $requestInterceptors[] = $interceptor;
            self::$requestInterceptors[$request] = $requestInterceptors;

            events()->networkInterceptorStart($request, $interceptor);

            $response = $interceptor->requestViaNetwork($request, $cancellation, $this->stream);

            events()->networkInterceptorEnd($request, $interceptor, $response);

            return $response;
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
