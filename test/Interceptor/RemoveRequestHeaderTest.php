<?php

namespace Amp\Http\Client\Interceptor;


use Amp\Http\Client\Request;

class RemoveRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): \Generator
    {
        $this->givenNetworkInterceptor(new RemoveRequestHeader('foo'));

        $request = new Request('http://example.org/');
        $request->addHeader('foo', 'bar');

        yield $this->whenRequestIsExecuted($request);

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): \Generator
    {
        $this->givenApplicationInterceptor(new RemoveRequestHeader('foo'));

        $request = new Request('http://example.org/');
        $request->addHeader('foo', 'bar');

        yield $this->whenRequestIsExecuted($request);

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
