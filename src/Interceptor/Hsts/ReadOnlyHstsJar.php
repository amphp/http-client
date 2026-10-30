<?php

namespace Amp\Http\Client\Interceptor\Hsts;

class ReadOnlyHstsJar implements ReadableHstsJar
{
    public function __construct(private ReadableHstsJar $proxyJar)
    {
    }

    public function test(string $host): bool
    {
        return $this->proxyJar->test($host);
    }
}
