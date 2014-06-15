<?php

namespace Artax;

class NativeDnsResolver implements DnsResolver {

    /**
     * Resolve a name to an IP address and invoke a callback with the result
     *
     * @param string $name The name to resolve
     * @param callable $callback
     */
    public function resolve($name, callable $callback) {
        if (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $callback($name, self::ADDR_INET4);
        } else if (filter_var($name, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $callback($name, self::ADDR_INET6);
        } else if ($name !== $addr = gethostbyname($name)) {
            $callback($addr, self::ADDR_INET4);
        } else {
            $callback(NULL, 0);
        }
    }

}
