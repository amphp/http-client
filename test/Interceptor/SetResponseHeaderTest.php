<?php

namespace Amp\Http\Client\Interceptor;

class SetResponseHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'bar'));
        $this->givenNetworkInterceptor(new AddResponseHeader('foo', 'baz'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptor(): \Generator
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'bar'));
        $this->givenApplicationInterceptor(new AddResponseHeader('foo', 'baz'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }
}
