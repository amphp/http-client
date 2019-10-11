<?php

namespace Amp\Http\Client\Connection;

use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Promise;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Connection
{
    public const MAX_KEEP_ALIVE_TIMEOUT = 60;

    /**
     * @return Promise<Stream>
     */
    public function getStream(): Promise;

    /**
     * @param Request $request
     *
     * @return Promise<Stream|null> Returns a stream for the given request, or null if no stream is available or if
     *                              the connection is not suited for the given request.
     *
     * @throws SocketException If all available streams have been used. Verify a stream is available with isBusy().
     */
    public function getStreamFor(Request $request): Promise;

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
