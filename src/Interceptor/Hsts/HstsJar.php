<?php

namespace Amp\Http\Client\Interceptor\Hsts;

interface HstsJar extends ReadableHstsJar
{
    /**
     * Mark a host as HSTS.
     * @param bool $includeSubDomains Whether the includeSubDomains directive was specified
     */
    public function register(string $host, bool $includeSubDomains = false): void;

    /**
     * Un-mark a host as HSTS, if it exists.
     */
    public function unregister(string $host): void;
}
