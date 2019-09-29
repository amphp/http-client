<?php

namespace Amp\Http\Client\Interceptor;

class AddRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new AddRequestHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): \Generator
    {
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
