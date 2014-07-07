<?php

namespace Artax;

use Addr\AddressModes;
use Alert\Reactor,
    Addr\Resolver,
    Addr\ResolverFactory;

class AddrDnsResolver implements DnsResolver {

    /**
     * @var Resolver
     */
    private $resolver;

    /**
     * @var int[]
     */
    private $familyMap = [
        AddressModes::INET4_ADDR => self::ADDR_INET4,
        AddressModes::INET6_ADDR => self::ADDR_INET6,
    ];

    /**
     * Constructor
     *
     * @param Reactor|Resolver $reactorOrResolver
     * @throws \InvalidArgumentException
     */
    public function __construct($reactorOrResolver) {
        if ($reactorOrResolver instanceof Resolver) {
            $this->resolver = $reactorOrResolver;
        } else if ($reactorOrResolver instanceof Reactor) {
            $this->resolver = (new ResolverFactory)->createResolver($reactorOrResolver);
        } else {
            throw new \InvalidArgumentException('Argument must be an instance of Alert\Reactor or Addr\Resolver');
        }
    }

    /**
     * Resolve a name to an IP address and invoke a callback with the result
     *
     * @param string $name The name to resolve
     * @param callable $callback
     */
    public function resolve($name, callable $callback) {
        $this->resolver->resolve($name, function($addr, $type) use($callback) {
            $type = $addr !== null ? $this->familyMap[$type] : $type;
            $callback($addr, $type);
        });
    }

}
