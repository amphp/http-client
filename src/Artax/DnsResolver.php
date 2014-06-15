<?php

namespace Artax;

interface DnsResolver {

    /**
     * Address family constants
     */
    const ADDR_INET4 = 1;
    const ADDR_INET6 = 2;

    /**
     * Resolve a name to an IP address and invoke a callback with the result
     *
     * Callback signature: void function (string $address, int $family)
     *   $address - resolved IP as a string or NULL if no record was found
     *   $family  - DnsResolver::ADDR_* constant
     *
     * @param string $name The name to resolve
     * @param callable $callback
     */
    public function resolve($name, callable $callback);

}
