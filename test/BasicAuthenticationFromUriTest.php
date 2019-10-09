<?php

namespace Amp\Http\Client\Test;

use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Interceptor\BasicAuthenticationFromUri;
use Amp\Http\Client\Request;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;
use Amp\Success;

class BasicAuthenticationFromUriTest extends AsyncTestCase
{
    public function testAuthorizationHeaderSetFromUri(): Promise
    {
        $interceptor = new BasicAuthenticationFromUri;
        $request = new Request('http://username:password@127.0.0.1/');

        $stream = $this->createMock(Stream::class);
        $stream->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (Request $request): Promise {
                $this->assertSame(
                    ['authorization' => ['Basic ' . \base64_encode('username:password')]],
                    $request->getHeaders()
                );
                return new Success; // Result unused, so resolving with null is fine.
            });

        return $interceptor->requestViaNetwork($request, $this->createMock(CancellationToken::class), $stream);
    }

    public function testRequestWithExistingAuthorizationHeader(): Promise
    {
        $interceptor = new BasicAuthenticationFromUri;
        $request = new Request('http://username:password@127.0.0.1/');
        $request->setHeader('Authorization', 'Test');

        $stream = $this->createMock(Stream::class);
        $stream->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (Request $request): Promise {
                $this->assertSame(
                    ['authorization' => ['Test']],
                    $request->getHeaders()
                );
                return new Success; // Result unused, so resolving with null is fine.
            });

        return $interceptor->requestViaNetwork($request, $this->createMock(CancellationToken::class), $stream);
    }
}
