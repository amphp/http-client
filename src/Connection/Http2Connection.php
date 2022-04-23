<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\Http\Client\Connection\Internal\Http2ConnectionProcessor;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;

final class Http2Connection implements Connection
{
    use ForbidSerialization;
    use ForbidCloning;

    private const PROTOCOL_VERSIONS = ['2'];

    private EncryptableSocket $socket;

    private Http2ConnectionProcessor $processor;

    private int $requestCount = 0;

    public function __construct(EncryptableSocket $socket)
    {
        $this->socket = $socket;
        $this->processor = new Http2ConnectionProcessor($socket);
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function initialize(): void
    {
        $this->processor->initialize();
    }

    public function getStream(Request $request): ?Stream
    {
        if (!$this->processor->isInitialized()) {
            throw new \Error('The promise returned from ' . __CLASS__ . '::initialize() must resolve before using the connection');
        }

        if ($this->processor->isClosed() || $this->processor->getRemainingStreams() <= 0) {
            return null;
        }

        $this->processor->reserveStream();

        return HttpStream::fromConnection(
            $this,
            \Closure::fromCallable([$this, 'request']),
            \Closure::fromCallable([$this->processor, 'unreserveStream'])
        );
    }

    public function onClose(callable $onClose): void
    {
        $this->processor->onClose($onClose);
    }

    public function close(): void
    {
        $this->processor->close();
    }

    public function isClosed(): bool
    {
        return $this->processor->isClosed();
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket->getTlsInfo();
    }

    private function request(Request $request, Cancellation $token, Stream $applicationStream): Response
    {
        $this->requestCount++;

        return $this->processor->request($request, $token, $applicationStream);
    }
}
