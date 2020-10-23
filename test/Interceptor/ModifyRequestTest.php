<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class ModifyRequestTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        $this->givenNetworkInterceptor(new ModifyRequest(static function (Request $request) {
            $request->setHeader('foo', 'bar');
        }));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptor(): void
    {
        $this->givenApplicationInterceptor(new ModifyRequest(static function (Request $request) {
            $request->setHeader('foo', 'bar');
        }));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
    }
}
