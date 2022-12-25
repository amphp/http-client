<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

class SetResponseHeaderTest extends InterceptorTest
{
    public function testNetworkInterceptor(): void
    {
        // execution order is reversed
        $this->givenNetworkInterceptor(new SetResponseHeader('foo', 'bar'));
        $this->givenNetworkInterceptor(new AddResponseHeader('foo', 'baz'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptor(): void
    {
        // execution order is reversed
        $this->givenApplicationInterceptor(new SetResponseHeader('foo', 'bar'));
        $this->givenApplicationInterceptor(new AddResponseHeader('foo', 'baz'));

        $this->whenRequestIsExecuted();

        $this->thenRequestDoesNotHaveHeader('foo');
        $this->thenResponseHasHeader('foo', 'bar');
    }
}
