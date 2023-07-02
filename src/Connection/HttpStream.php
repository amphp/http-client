<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use function Amp\Http\Client\processRequest;

final class HttpStream implements Stream
{
    use ForbidSerialization;
    use ForbidCloning;

    public static function fromConnection(
        Connection $connection,
        callable $requestCallback,
        callable $releaseCallback
    ): self {
        return new self(
            $connection->getLocalAddress(),
            $connection->getRemoteAddress(),
            $connection->getTlsInfo(),
            $requestCallback,
            $releaseCallback
        );
    }

    public static function fromStream(Stream $stream, callable $requestCallback, callable $releaseCallback): self
    {
        return new self(
            $stream->getLocalAddress(),
            $stream->getRemoteAddress(),
            $stream->getTlsInfo(),
            $requestCallback,
            $releaseCallback
        );
    }

    private SocketAddress $localAddress;

    private SocketAddress $remoteAddress;

    private ?TlsInfo $tlsInfo;

    /** @var callable */
    private $requestCallback;

    /** @var callable|null */
    private $releaseCallback;

    private function __construct(
        SocketAddress $localAddress,
        SocketAddress $remoteAddress,
        ?TlsInfo $tlsInfo,
        callable $requestCallback,
        callable $releaseCallback
    ) {
        $this->localAddress = $localAddress;
        $this->remoteAddress = $remoteAddress;
        $this->tlsInfo = $tlsInfo;
        $this->requestCallback = $requestCallback;
        $this->releaseCallback = $releaseCallback;
    }

    public function __destruct()
    {
        if ($this->releaseCallback !== null) {
            ($this->releaseCallback)();
        }
    }

    /**
     * @throws HttpException
     */
    public function request(Request $request, Cancellation $cancellation): Response
    {
        if ($this->releaseCallback === null) {
            throw new \Error('A stream may only be used for a single request');
        }

        $this->releaseCallback = null;

        return processRequest($request, [], fn (): Response => ($this->requestCallback)($request, $cancellation, $this));
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->localAddress;
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->remoteAddress;
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->tlsInfo;
    }
}
