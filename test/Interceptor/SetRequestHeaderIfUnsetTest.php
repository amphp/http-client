<?php

namespace Amp\Http\Client\Interceptor;

class SetRequestHeaderIfUnsetTest extends InterceptorTest
{
    public function testNetworkInterceptorIfSet(): void
    {
        $this->givenNetworkInterceptor(new SetRequestHeader('foo', 'baz'));
        $this->givenNetworkInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptorIfSet(): void
    {
        $this->givenApplicationInterceptor(new SetRequestHeader('foo', 'baz'));
        $this->givenApplicationInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testNetworkInterceptorIfUnset(): void
    {
        $this->givenNetworkInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptorIfUnset(): void
    {
        $this->givenApplicationInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
