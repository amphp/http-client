<?php

namespace Amp\Http\Client\Test\Interceptor;

use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Interceptor\SetRequestHeaderIfUnset;

class SetRequestHeaderIfUnsetTest extends InterceptorTest
{
    public function testNetworkInterceptorIfSet(): \Generator
    {
        $this->givenNetworkInterceptor(new SetRequestHeader('foo', 'baz'));
        $this->givenNetworkInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptorIfSet(): \Generator
    {
        $this->givenApplicationInterceptor(new SetRequestHeader('foo', 'baz'));
        $this->givenApplicationInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testNetworkInterceptorIfUnset(): \Generator
    {
        $this->givenNetworkInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptorIfUnset(): \Generator
    {
        $this->givenApplicationInterceptor(new SetRequestHeaderIfUnset('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
