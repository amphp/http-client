<?php

namespace Amp\Http\Client\Interceptor\Hsts;

interface ReadableHstsJar
{
    /**
     * Test whether a host is registered as HSTS.
     */
    public function test(string $host): bool;
}
