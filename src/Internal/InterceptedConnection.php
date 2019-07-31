<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Failure;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

final class InterceptedConnection implements Connection
{
    /** @var Connection */
    private $connection;
    /** @var NetworkInterceptor[] */
    private $interceptors;
    /** @var bool */
    private $called = false;

    public function __construct(Connection $connection, NetworkInterceptor... $interceptors)
    {
        $this->connection = $connection;
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

            return $this->connection->request($request, $cancellation);
        }

        $interceptor = \array_shift($this->interceptors);

        return $interceptor->interceptNetworkRequest($request, $cancellation, $this);
    }

    public function isBusy(): bool
    {
        return $this->connection->isBusy();
    }

    public function close(): Promise
    {
        return $this->connection->close();
    }

    public function onClose(callable $onClose): void
    {
        $this->connection->onClose($onClose);
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->connection->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->connection->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->connection->getTlsInfo();
    }
}
