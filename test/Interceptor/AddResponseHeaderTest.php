<?php

namespace Amp\Http\Client\Interceptor;

class AddResponseHeaderTest extends InterceptorTest
{

    public function testNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new AddResponseHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptor(): \Generator
    {
        $this->givenApplicationInterceptor(new AddResponseHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }
}
