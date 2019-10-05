<?php

namespace Amp\Http\Client\Interceptor;

class SetResponseHeaderIfUnsetTest extends InterceptorTest
{
    public function testNetworkInterceptorIfSet(): \Generator
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'baz'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'baz');
    }

    public function testApplicationInterceptorIfSet(): \Generator
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'baz'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'baz');
    }

    public function testNetworkInterceptorIfUnset(): \Generator
    {
        $this->givenNetworkInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptorIfUnset(): \Generator
    {
        $this->givenApplicationInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }
}
