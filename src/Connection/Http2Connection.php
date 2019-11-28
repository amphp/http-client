<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Internal\InternalHttp2Connection;
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

    /** @var InternalHttp2Connection */
    private $connection;

    /** @var int */
    private $requestCount = 0;

    public function __construct(EncryptableSocket $socket)
    {
        $this->socket = $socket;
        $this->connection = new InternalHttp2Connection($socket);
    }

    public function getProtocolVersions(): array
    {
        return self::PROTOCOL_VERSIONS;
    }

    public function initialize(): Promise
    {
        return $this->connection->initialize();
    }

    public function getStream(Request $request): Promise
    {
        if (!$this->connection->isInitialized()) {
            throw new \Error('The promise returned from ' . __CLASS__ . '::initialize() must resolve before using the connection');
        }

        return call(function () {
            if ($this->connection->isClosed() || $this->connection->getRemainingStreams() <= 0) {
                return null;
            }

            $this->connection->reserveStream();

            return HttpStream::fromConnection(
                $this,
                \Closure::fromCallable([$this, 'request']),
                \Closure::fromCallable([$this->connection, 'unreserveStream'])
            );
        });
    }

    public function onClose(callable $onClose): void
    {
        $this->connection->onClose($onClose);
    }

    public function close(): Promise
    {
        return $this->connection->close();
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

        return $this->connection->request($request, $token, $applicationStream);
    }
}
