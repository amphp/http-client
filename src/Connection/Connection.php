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
     * @param Request $request
     *
     * @return Stream
     *
     * @throws SocketException If all available streams have been used. Verify a stream is available with isBusy().
     */
    public function getStream(Request $request): Stream;

    /**
     * @return string[] Array of supported protocol versions.
     */
    public function getProtocolVersions(): array;

    /**
     * @return bool True if a stream is still available, false if the connection is completely busy.
     */
    public function isBusy(): bool;

    public function close(): Promise;

    public function onClose(callable $onClose): void;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
