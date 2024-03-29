<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class RemoveRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        $this->givenNetworkInterceptor(new RemoveRequestHeader('foo'));

        $request = new Request('http://example.org/');
        $request->addHeader('foo', 'bar');

        $this->whenRequestIsExecuted($request);

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): void
    {
        $this->givenApplicationInterceptor(new RemoveRequestHeader('foo'));

        $request = new Request('http://example.org/');
        $request->addHeader('foo', 'bar');

        $this->whenRequestIsExecuted($request);

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
