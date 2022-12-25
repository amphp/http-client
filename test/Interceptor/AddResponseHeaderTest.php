<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

class AddResponseHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new AddResponseHeader('foo', 'baz'));
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar', 'baz');
    }

    public function testApplicationInterceptor(): void
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new AddResponseHeader('foo', 'baz'));
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'bar'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar', 'baz');
    }
}
