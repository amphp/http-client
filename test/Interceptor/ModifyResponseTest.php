<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Response;

class ModifyResponseTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        $this->givenNetworkInterceptor(new ModifyResponse(static function (Response $response) {
            $response->setHeader('foo', 'bar');
        }));

        $this->whenRequestIsExecuted();

        $this->thenResponseHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptor(): void
    {
        $this->givenApplicationInterceptor(new ModifyResponse(static function (Response $request) {
            $request->setHeader('foo', 'bar');
        }));

        $this->whenRequestIsExecuted();

        $this->thenResponseHasHeader('foo', 'bar');
    }
}
