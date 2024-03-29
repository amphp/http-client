<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

class SetRequestHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        $this->givenNetworkInterceptor(new AddRequestHeader('foo', 'baz'));
        $this->givenNetworkInterceptor(new SetRequestHeader('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptor(): void
    {
        $this->givenApplicationInterceptor(new AddRequestHeader('foo', 'baz'));
        $this->givenApplicationInterceptor(new SetRequestHeader('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestHasHeader('foo', 'bar');
        $this->thenResponseDoesNotHaveHeader('foo');
    }
}
