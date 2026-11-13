<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Interceptor\Hsts\GooglePreloadListJar;
use Amp\Http\Client\Interceptor\Hsts\HstsInterceptor;
use Amp\Http\Client\Interceptor\Hsts\InMemoryHstsJar;

class HstsTest extends InterceptorTest
{
    public function testHstsHost(): void
    {
        $hstsJar = new InMemoryHstsJar();
        $hstsJar->register("example.org");
        $this->assertTrue($hstsJar->test("example.org"));
//        $this->givenApplicationInterceptor(new HstsInterceptor($hstsJar));
//        $this->whenRequestIsExecuted();
//        $this->thenRequestHasScheme("https");
    }
    public function testNonHstsHost(): void
    {
        $hstsJar = new InMemoryHstsJar();
        $hstsJar->register("example.com");
        $this->givenApplicationInterceptor(new HstsInterceptor($hstsJar));
        $this->whenRequestIsExecuted();
        $this->thenRequestHasScheme("http");
    }
    public function testPreloadList(): void
    {
        $hstsJar = new GooglePreloadListJar();
        $this->assertTrue($hstsJar->test("test.dev"));
    }
}
