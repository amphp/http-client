<?php

namespace Amp\Artax\Cookie;

class NullCookieJar implements CookieJar {
    public function get(string $domain, string $path = '', string $name = null): array {
        return [];
    }
    public function getAll(): array {
        return [];
    }
    public function store(Cookie $cookie) {
        return null;
    }
    public function remove(Cookie $cookie) {
        return null;
    }
    public function removeAll() {
        return null;
    }
}
