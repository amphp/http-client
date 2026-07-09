<?php

namespace Amp\Http\Client\Interceptor\Hsts;

class CombinationHstsJar implements HstsJar
{
    /**
     * @var ReadableHstsJar[]
     */
    private readonly array $jars;

    public function __construct(ReadableHstsJar ...$jars)
    {
        $this->jars = $jars;
    }

    public function test(string $host): bool
    {
        foreach ($this->jars as $jar) {
            if ($jar->test($host)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Registers into first HSTS jar that is not read-only.
     */
    public function register(string $host, bool $includeSubDomains = false): void
    {
        foreach ($this->jars as $jar) {
            if ($jar instanceof HstsJar) {
                $jar->register($host, $includeSubDomains);
                return;
            }
        }
    }

    /**
     * Unregisters from all HSTS jars.
     */
    public function unregister(string $host): void
    {
        foreach ($this->jars as $jar) {
            if ($jar instanceof HstsJar) {
                $jar->unregister($host);
                return;
            }
        }
    }
}
