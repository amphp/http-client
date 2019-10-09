<?php

namespace Amp\Http\Client\Test\Interceptor;

use Amp\Http\Client\Interceptor\AddRequestHeader;
use Amp\Http\Client\Interceptor\SetRequestHeader;

class AddRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new SetRequestHeader('foo', 'bar'));
        $this->givenNetworkInterceptor(new AddRequestHeader('foo', 'baz'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): \Generator
    {
        $this->givenApplicationInterceptor(new SetRequestHeader('foo', 'bar'));
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'baz'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
