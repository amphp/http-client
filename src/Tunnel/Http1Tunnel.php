<?php

namespace Amp\Http\Client\Tunnel;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Http1Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use function Amp\call;
use function Amp\Socket\connector;

final class Http1Tunnel implements Connector
{
    use ForbidCloning;
    use ForbidSerialization;

    public static function tunnel(EncryptableSocket $socket, string $target, array $customHeaders): Promise
    {
        return call(static function () use ($socket, $target, $customHeaders) {
            $request = new Request('http://' . \str_replace('tcp://', '', $target), 'CONNECT');
            $request->setHeaders($customHeaders);
            $request->setUpgradeHandler(static function (EncryptableSocket $socket) use (&$upgradedSocket) {
                $upgradedSocket = $socket;
            });

            $connection = new Http1Connection($socket, 1000);

            /** @var Stream $stream */
            $stream = yield $connection->getStream($request);

            /** @var Response $response */
            $response = yield $stream->request($request, new NullCancellationToken);

            if ($response->getStatus() !== 200) {
                throw new ConnectException('Failed to connect to proxy: Received a bad status code (' . $response->getStatus() . ')');
            }

            \assert($upgradedSocket !== null);

            return $upgradedSocket;
        });
    }

    /** @var string */
    private $proxyUri;
    /** @var array */
    private $customHeaders;
    /** @var Connector|null */
    private $connector;

    public function __construct(SocketAddress $proxyAddress, array $customHeaders = [], ?Connector $connector = null)
    {
        $this->proxyUri = (string) $proxyAddress;
        $this->customHeaders = $customHeaders;
        $this->connector = $connector;
    }

    public function connect(string $uri, ?ConnectContext $context = null, ?CancellationToken $token = null): Promise
    {
        return call(function () use ($uri, $context, $token) {
            $connector = $this->connector ?? connector();

            $socket = yield $connector->connect($this->proxyUri, $context, $token);

            return self::tunnel($socket, $uri, $this->customHeaders);
        });
    }
}
