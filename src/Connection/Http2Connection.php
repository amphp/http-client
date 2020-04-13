<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Internal\Http2ConnectionProcessor;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use function Amp\call;

final class Http2Connection implements Connection
{
    use ForbidSerialization;
    use ForbidCloning;

    private const PROTOCOL_VERSIONS = ['2'];

    /** @var EncryptableSocket */
    private $socket;

    /** @var Http2ConnectionProcessor */
    private $processor;

    /** @var int */
    private $requestCount = 0;

    public function __construct(EncryptableSocket $socket)
    {
        $this->socket = $socket;
        $this->processor = new Http2ConnectionProcessor($socket);
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function initialize(): Promise
    {
        return $this->processor->initialize();
    }

    public function getStream(Request $request): Promise
    {
        if (!$this->processor->isInitialized()) {
            throw new \Error('The promise returned from ' . __CLASS__ . '::initialize() must resolve before using the connection');
        }

        return call(function () {
            if ($this->processor->isClosed() || $this->processor->getRemainingStreams() <= 0) {
                return null;
            }

            $this->processor->reserveStream();

            return HttpStream::fromConnection(
                $this,
                \Closure::fromCallable([$this, 'request']),
                \Closure::fromCallable([$this->processor, 'unreserveStream'])
            );
        });
    }

    public function onClose(callable $onClose): void
    {
        $this->processor->onClose($onClose);
    }

    public function close(): Promise
    {
        return $this->processor->close();
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

    private function request(Request $request, CancellationToken $token, Stream $applicationStream): Promise
    {
        $this->requestCount++;

        return $this->processor->request($request, $token, $applicationStream);
    }
}
