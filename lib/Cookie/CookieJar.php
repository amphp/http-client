<?php

namespace Amp\Artax\Cookie;

interface CookieJar {
    public function get($domain, $path = '', $name = null);
    public function getAll();
    public function store(Cookie $cookie);
    public function remove(Cookie $cookie);
    public function removeAll();
}
