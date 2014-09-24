<?php

namespace Amp\Artax\Cookie;

class NullCookieJar implements CookieJar {
    public function get($domain, $path = '', $name = null) {
        return [];
    }
    public function getAll() {
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
