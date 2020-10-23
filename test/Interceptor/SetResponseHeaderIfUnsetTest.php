<?php

namespace Amp\Http\Client\Interceptor;

class SetResponseHeaderIfUnsetTest extends InterceptorTest
{
    public function testNetworkInterceptorIfSet(): void
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'baz'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'baz');
    }

    public function testApplicationInterceptorIfSet(): void
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'baz'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'baz');
    }

    public function testNetworkInterceptorIfUnset(): void
    {
        $this->givenNetworkInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptorIfUnset(): void
    {
        $this->givenApplicationInterceptor(new SetResponseHeaderIfUnset('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }
}
