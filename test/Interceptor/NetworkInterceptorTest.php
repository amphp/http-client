<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Promise;
use PHPUnit\Framework\TestCase;

class NetworkInterceptorTest extends InterceptorTest
{
    public function testGetters(): \Generator
    {
        $this->givenNetworkInterceptor(new class($this) implements NetworkInterceptor {
            /** @var NetworkInterceptorTest */
            private $testCase;

            public function __construct(NetworkInterceptorTest $testCase)
            {
                $this->testCase = $testCase;
            }

            public function requestViaNetwork(
                Request $request,
                CancellationToken $cancellation,
                Stream $stream
            ): Promise {
                TestCase::assertNull($stream->getTlsInfo());
                TestCase::assertSame(
                    $this->testCase->getServerAddress()->toString(),
                    $stream->getRemoteAddress()->toString()
                );
                TestCase::assertSame('127.0.0.1', $stream->getLocalAddress()->getHost());
                TestCase::assertNotSame(
                    $this->testCase->getServerAddress()->toString(),
                    $stream->getLocalAddress()->toString()
                );

                return $stream->request($request, $cancellation);
            }
        });

        // dummy interceptor to have nested network interceptors
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->assertTrue(true); // No exception in interceptor
    }
}
