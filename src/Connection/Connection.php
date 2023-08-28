<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Closable;
use Amp\Http\Client\Request;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

interface Connection extends Closable
{
    /**
     * @return Stream|null Returns a stream for the given request, or null if no stream is available or if
     *                     the connection is not suited for the given request. The first request for a stream
     *                     on a new connection MUST return a Stream instance.
     */
    public function getStream(Request $request): ?Stream;

    public function isIdle(): bool;

    /**
     * @return string[] Array of supported protocol versions.
     */
    public function getProtocolVersions(): array;

    public function getLocalAddress(): SocketAddress;

    public function getRemoteAddress(): SocketAddress;

    public function getTlsInfo(): ?TlsInfo;

    /**
     * @return float|null Returns the TLS handshake duration if applicable in seconds.
     */
    public function getTlsHandshakeDuration(): ?float;

    /**
     * @return float Returns the total connect duration in seconds, including the TLS handshake.
     */
    public function getConnectDuration(): float;
}
