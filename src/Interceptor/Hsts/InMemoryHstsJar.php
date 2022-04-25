<?php

namespace Amp\Http\Client\Interceptor\Hsts;

final class InMemoryHstsJar implements HstsJar
{
    /**
     * Array of host to either true (includeSubDomain) or false (no includeSubDomain)
     * @var array<string,bool>
     */
    private array $hosts = [];

    public function test(string $host, bool $requireIncludeSubDomains = false): bool
    {
        if (
            // Host must have been marked HSTS
            array_key_exists($host, $this->hosts) &&
            // If "includeSubDomains" is required, it must be marked as such
            (!$requireIncludeSubDomains || $this->hosts[$host])
        ) {
            return true;
        }
        if (($dotPosition = strpos($host, ".")) !== false) {
            // Test if a parent domain has been registered with includeSubDomains
            return $this->test(substr($host, $dotPosition + 1), true);
        }
        return false;
    }

    public function register(string $host, bool $includeSubDomains = false): void
    {
        $this->hosts[$host] = $includeSubDomains;
    }

    public function unregister(string $host): void
    {
        unset($this->hosts[$host]);
    }
}
