<?php

namespace Amp\Http\Client\Connection;

use Amp\Failure;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\NullCancellationToken;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Socket;
use Amp\TimeoutException;
use function Amp\call;

class DefaultConnectionFactoryTest extends AsyncTestCase
{
    public function testConnectCallbackIsCalledBeforeConnecting(): \Generator
    {
        $server = Socket\Server::listen('127.0.0.1:0');
        $serverAddress = $server->getAddress()->toString();

        $request = new Request('http://' . $serverAddress . '/');
        $request->setConnectCallback(static function (Socket\SocketAddress $address) {
            throw new \RuntimeException('Blocked ' . $address->toString());
        });

        try {
            yield (new HttpClientBuilder)->retry(0)->build()->request($request);
            $this->fail('The request should have been blocked');
        } catch (\RuntimeException $e) {
            $this->assertSame('Blocked ' . $serverAddress, $e->getMessage());
        }

        try {
            yield Promise\timeout($server->accept(), 100);
            $this->fail('No connection should have been opened');
        } catch (TimeoutException $e) {
            $server->close();
        }
    }

    public function testConnectCallbackOfTheConnectContextIsKept(): \Generator
    {
        $callback = static function () {
        };

        /** @var Socket\ConnectContext $connectContext */
        $connectContext = yield $this->getConnectContext(
            new Request('http://localhost/'),
            (new Socket\ConnectContext)->withConnectCallback($callback)
        );

        $this->assertSame($callback, $connectContext->getConnectCallback());
    }

    public function testConnectCallbackIsAddedToTheOneOfTheConnectContext(): \Generator
    {
        $calls = [];

        $request = new Request('http://localhost/');
        $request->setConnectCallback(static function (Socket\SocketAddress $address) use (&$calls) {
            $calls[] = 'request ' . $address->toString();
        });

        /** @var Socket\ConnectContext $connectContext */
        $connectContext = yield $this->getConnectContext(
            $request,
            (new Socket\ConnectContext)->withConnectCallback(static function (Socket\SocketAddress $address) use (&$calls) {
                $calls[] = 'context ' . $address->toString();
            })
        );

        yield call($connectContext->getConnectCallback(), new Socket\SocketAddress('127.0.0.1', 80));

        $this->assertSame(['context 127.0.0.1:80', 'request 127.0.0.1:80'], $calls);
    }

    private function getConnectContext(Request $request, Socket\ConnectContext $connectContext): Promise
    {
        return call(function () use ($request, $connectContext) {
            $passedConnectContext = null;

            $connector = $this->createMock(Socket\Connector::class);
            $connector->expects($this->once())
                ->method('connect')
                ->willReturnCallback(static function (string $uri, Socket\ConnectContext $connectContext) use (&$passedConnectContext): Promise {
                    $passedConnectContext = $connectContext;

                    return new Failure(new Socket\ConnectException('Connection refused'));
                });

            try {
                yield (new DefaultConnectionFactory($connector, $connectContext))->create($request, new NullCancellationToken);
                $this->fail('The connection should have failed');
            } catch (UnprocessedRequestException $e) {
                // The connector always fails
            }

            return $passedConnectContext;
        });
    }
}
