<?php

namespace Amp\Http\Client;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Http1Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket\ConnectContext;
use Amp\Socket\ConnectException;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\SocketAddress;
use function Amp\call;
use function Amp\Socket\connect;

final class Http1Tunnel implements Connector
{
    private $proxyUri;
    private $customHeaders;

    public function __construct(SocketAddress $proxyAddress, array $customHeaders = [])
    {
        $this->proxyUri = (string) $proxyAddress;
        $this->customHeaders = $customHeaders;
    }

    public function connect(string $uri, ?ConnectContext $context = null, ?CancellationToken $token = null): Promise
    {
        return call(function () use ($uri, $context, $token) {
            $socket = yield connect($this->proxyUri, $context, $token);

            $request = new Request('http://' . \str_replace('tcp://', '', $uri), 'CONNECT');
            $request->setHeaders($this->customHeaders);
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
}
