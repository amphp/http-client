<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\CancelledException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\NullCancellation;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Socket;
use Amp\Socket\ConnectContext;
use Amp\Socket\InternetAddress;
use Amp\TimeoutCancellation;

class DefaultConnectionFactoryTest extends AsyncTestCase
{
    public function testConnectCallbackIsCalledBeforeConnecting(): void
    {
        $server = Socket\listen('127.0.0.1:0');
        $serverAddress = (string) $server->getAddress();

        $request = new Request('http://' . $serverAddress . '/');
        $request->setTransferTimeout(1);
        $request->setConnectCallback(static function (InternetAddress $address): void {
            throw new \RuntimeException('Blocked ' . $address->toString());
        });

        try {
            (new HttpClientBuilder)->retry(0)->build()->request($request);
            self::fail('The request should have been blocked');
        } catch (\RuntimeException $e) {
            self::assertSame('Blocked ' . $serverAddress, $e->getMessage());
        }

        try {
            $server->accept(new TimeoutCancellation(0.1));
            self::fail('No connection should have been opened');
        } catch (CancelledException) {
            $server->close();
        }
    }

    public function testConnectCallbackIsAddedToTheOneOfTheConnectContext(): void
    {
        $calls = [];

        $connector = $this->createMock(Socket\SocketConnector::class);
        $connector->expects(self::once())
            ->method('connect')
            ->willReturnCallback(static function (string $uri, ConnectContext $context): Socket\Socket {
                $context->getConnectCallback()?->__invoke(new InternetAddress('127.0.0.1', 80));

                throw new Socket\ConnectException('Connection refused');
            });

        $factory = new DefaultConnectionFactory(
            $connector,
            (new ConnectContext)->withConnectCallback(static function (InternetAddress $address) use (&$calls): void {
                $calls[] = 'context ' . $address->toString();
            }),
        );

        $request = new Request('http://localhost/');
        $request->setConnectCallback(static function (InternetAddress $address) use (&$calls): void {
            $calls[] = 'request ' . $address->toString();
        });

        try {
            $factory->create($request, new NullCancellation);
            self::fail('The connection should have failed');
        } catch (SocketException) {
            self::assertSame(['context 127.0.0.1:80', 'request 127.0.0.1:80'], $calls);
        }
    }
}
