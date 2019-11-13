<?php

namespace Amp\Http\Client\Tunnel;

use Amp\CancellationToken;
use Amp\Http\Client\Tunnel\Internal\TunnelSocket;
use Amp\Promise;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\Connector;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\Server;
use Amp\Socket\SocketAddress;
use function Amp\asyncCall;
use function Amp\ByteStream\pipe;
use function Amp\call;
use function Amp\Socket\connect;
use function Amp\Socket\connector;

final class Https1Tunnel implements Connector
{
    /** @var string */
    private $proxyUri;
    /** @var ClientTlsContext */
    private $proxyTls;
    /** @var array[] */
    private $customHeaders;
    /** @var Connector|null */
    private $connector;

    public function __construct(SocketAddress $proxyAddress, ?ClientTlsContext $proxyTls = null, array $customHeaders = [], ?Connector $connector = null)
    {
        $this->proxyUri = (string) $proxyAddress;
        $this->proxyTls = $proxyTls ?? new ClientTlsContext($proxyAddress->getHost());
        $this->customHeaders = $customHeaders;
        $this->connector = $connector;
    }

    public function connect(string $uri, ?ConnectContext $context = null, ?CancellationToken $token = null): Promise
    {
        return call(function () use ($uri, $context, $token) {
            $connector = $this->connector ?? connector();

            /** @var EncryptableSocket $remoteSocket */
            $remoteSocket = yield $connector->connect($this->proxyUri, $context->withTlsContext($this->proxyTls), $token);

            yield $remoteSocket->setupTls($token);

            $remoteSocket = yield Http1Tunnel::tunnel($remoteSocket, $uri, $this->customHeaders);

            /** @var EncryptableSocket $serverSocket */
            /** @var EncryptableSocket $clientSocket */
            [$serverSocket, $clientSocket] = yield $this->createPair((new ConnectContext)->withTlsContext($context->getTlsContext()));

            asyncCall(static function () use ($serverSocket, $remoteSocket) {
                try {
                    yield [
                        pipe($serverSocket, $remoteSocket),
                        pipe($remoteSocket, $serverSocket),
                    ];
                } catch (\Throwable $e) {
                    // ignore
                } finally {
                    $serverSocket->close();
                    $remoteSocket->close();
                }
            });

            return new TunnelSocket($clientSocket, $remoteSocket);
        });
    }

    private function createPair(ConnectContext $connectContext): Promise
    {
        return call(static function () use ($connectContext) {
            retry:

            $server = Server::listen('127.0.0.1:0');
            $address = (string) $server->getAddress();

            $connectPromise = connect($address, $connectContext);

            try {
                /** @var EncryptableSocket $serverSocket */
                /** @var EncryptableSocket $clientSocket */
                [$serverSocket, $clientSocket] = yield [
                    $server->accept(),
                    $connectPromise,
                ];
            } finally {
                $server->close();
            }

            if ((string) $serverSocket->getRemoteAddress() !== (string) $clientSocket->getLocalAddress()) {
                goto retry; // someone else connected faster...
            }

            return [$serverSocket, $clientSocket];
        });
    }
}
