<?php

namespace Amp\Http\Client\Test\Interceptor;

use Amp\Http\Client\Interceptor\AddResponseHeader;
use Amp\Http\Client\Interceptor\SetResponseHeader;

class AddResponseHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new AddResponseHeader('foo', 'baz'));
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar', 'baz');
    }

    public function testApplicationInterceptor(): \Generator
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new AddResponseHeader('foo', 'baz'));
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'bar'));

        yield $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar', 'baz');
    }
}
