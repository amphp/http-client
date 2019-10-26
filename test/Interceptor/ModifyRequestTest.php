<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class ModifyRequestTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new ModifyRequest(static function (Request $request) {
            $request->setHeader('foo', 'bar');
        }));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptor(): \Generator
    {
        $this->givenApplicationInterceptor(new ModifyRequest(static function (Request $request) {
            $request->setHeader('foo', 'bar');
        }));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
    }
}
