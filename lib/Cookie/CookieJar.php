<?php

namespace Amp\Artax\Cookie;

interface CookieJar {
    public function get($domain, $path = '', $name = null);
    public function getAll();

    /**
     * Note: Implicit domains MUST NOT start with a dot, explicitly set domains MUST start with a dot.
     *
     * @param Cookie $cookie
     */
    public function store(Cookie $cookie);
    public function remove(Cookie $cookie);
    public function removeAll();
}
