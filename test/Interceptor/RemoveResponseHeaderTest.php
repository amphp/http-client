<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class RemoveResponseHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new RemoveResponseHeader('foo'));
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'bar'));

        $request = new Request('http://example.org/');

        $this->whenRequestIsExecuted($request);

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): void
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new RemoveResponseHeader('foo'));
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'bar'));

        $request = new Request('http://example.org/');

        $this->whenRequestIsExecuted($request);

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
