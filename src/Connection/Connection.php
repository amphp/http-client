<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Connection
{
    /**
     * @param Request $request
     *
     * @return Promise<Stream|null> Returns a stream for the given request, or null if no stream is available or if
     *                              the connection is not suited for the given request. The first request for a stream
     *                              on a new connection MUST resolve the promise with a Stream instance.
     */
    public function getStream(Request $request): Promise;

    /**
     * @return string[] Array of supported protocol versions.
     */
    public function getProtocolVersions(): array;

    public function close(): Promise;

    public function onClose(callable $onClose): void;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
