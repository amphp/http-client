<?php

namespace Amp\Http\Client\Interceptor;

class SetRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new AddRequestHeader('foo', 'baz'));
        $this->givenNetworkInterceptor(new SetRequestHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): \Generator
    {
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'baz'));
        $this->givenApplicationInterceptor(new SetRequestHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
