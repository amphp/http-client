<?php

namespace Amp\Http\Client\Connection;

use Amp\Closable;
use Amp\Http\Client\Request;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Connection extends Closable
{
    /**
     * @return Stream|null Returns a stream for the given request, or null if no stream is available or if
     *                     the connection is not suited for the given request.
     */
    public function getStream(Request $request): ?Stream;

    /**
     * @return string[] Array of supported protocol versions.
     */
    public function getProtocolVersions(): array;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;
}
