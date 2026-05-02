<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\Connection\Internal\Http2ConnectionProcessor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\TimeoutCancellation;
use function Amp\Http\Client\events;

final class Http2Connection implements Connection
{
    use ForbidSerialization;
    use ForbidCloning;

    private const PROTOCOL_VERSIONS = ['2'];

    private readonly Http2ConnectionProcessor $processor;

    private int $streamCounter = 0;

    private int $requestCount = 0;

    public function __construct(
        private readonly Socket $socket,
        private readonly float $connectDuration,
        private readonly ?float $tlsHandshakeDuration
    ) {
        $this->processor = new Http2ConnectionProcessor($socket);
    }

    #[\Override]
    public function isIdle(): bool
    {
        return $this->processor->isIdle();
    }

    #[\Override]
    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function initialize(?Cancellation $cancellation = null): void
    {
        $this->processor->initialize($cancellation ?? new TimeoutCancellation(5));
    }

    #[\Override]
    public function getStream(Request $request): ?Stream
    {
        if (!$this->processor->isInitialized()) {
            throw new \Error('The ' . __CLASS__ . '::initialize() invocation must be complete before using the connection');
        }

        if ($this->processor->isClosed() || $this->processor->getRemainingStreams() <= 0) {
            return null;
        }

        $this->processor->reserveStream();

        events()->connectionAcquired($request, $this, ++$this->streamCounter);

        return HttpStream::fromConnection($this, $this->request(...), $this->processor->unreserveStream(...));
    }

    #[\Override]
    public function onClose(\Closure $onClose): void
    {
        $this->processor->onClose($onClose);
    }

    #[\Override]
    public function close(): void
    {
        $this->processor->close();
    }

    #[\Override]
    public function isClosed(): bool
    {
        return $this->processor->isClosed();
    }

    #[\Override]
    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    #[\Override]
    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    #[\Override]
    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket->getTlsInfo();
    }

    private function request(Request $request, Cancellation $cancellation, Stream $stream): Response
    {
        $this->requestCount++;

        return $this->processor->request($request, $cancellation, $stream);
    }

    #[\Override]
    public function getTlsHandshakeDuration(): ?float
    {
        return $this->tlsHandshakeDuration;
    }

    #[\Override]
    public function getConnectDuration(): float
    {
        return $this->connectDuration;
    }
}
