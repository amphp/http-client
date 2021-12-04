<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Cancellation;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class NetworkInterceptorTest extends InterceptorTest
{
    public function testGetters(): void
    {
        $this->givenNetworkInterceptor(new class($this) implements NetworkInterceptor {
            private NetworkInterceptorTest $testCase;

            public function __construct(NetworkInterceptorTest $testCase)
            {
                $this->testCase = $testCase;
            }

            public function requestViaNetwork(
                Request $request,
                Cancellation $cancellation,
                Stream $stream
            ): Response {
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

        $this->whenRequestIsExecuted();

        $this->assertTrue(true); // No exception in interceptor
    }
}
