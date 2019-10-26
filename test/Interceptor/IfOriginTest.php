<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class IfOriginTest extends InterceptorTest
{
    public function testNetworkInterceptorMatches(): \Generator
    {
        $this->givenNetworkInterceptor(new IfOrigin('http://example.com/', new SetRequestHeader('foo', 'bar')));

        yield $this->whenRequestIsExecuted(new Request('http://example.com/foo/bar?test=1'));

        $this->thenRequestHasHeader('foo', 'bar');
    }

    public function testNetworkInterceptorDoesNotMatch(): \Generator
    {
        $this->givenNetworkInterceptor(new IfOrigin('http://example.com/', new SetRequestHeader('foo', 'bar')));

        yield $this->whenRequestIsExecuted(new Request('http://foobar.com/foo/bar?test=1'));

        $this->thenRequestDoesNotHaveHeader('foo');
    }

    public function testApplicationInterceptorMatches(): \Generator
    {
        $this->givenApplicationInterceptor(new IfOrigin('http://example.com/', new SetRequestHeader('foo', 'bar')));

        yield $this->whenRequestIsExecuted(new Request('http://example.com/foo/bar?test=1'));

        $this->thenRequestHasHeader('foo', 'bar');
    }

    public function testApplicationInterceptorDoesNotMatch(): \Generator
    {
        $this->givenApplicationInterceptor(new IfOrigin('http://example.com/', new SetRequestHeader('foo', 'bar')));

        yield $this->whenRequestIsExecuted(new Request('http://foobar.com/foo/bar?test=1'));

        $this->thenRequestDoesNotHaveHeader('foo');
    }
}
