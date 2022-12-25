<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

class AddRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        $this->givenNetworkInterceptor(new SetRequestHeader('foo', 'bar'));
        $this->givenNetworkInterceptor(new AddRequestHeader('foo', 'baz'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): void
    {
        $this->givenApplicationInterceptor(new SetRequestHeader('foo', 'bar'));
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'baz'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar', 'baz');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
