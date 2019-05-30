<?php

namespace Amp\Http\Client\Cookie;

interface CookieJar
{
    public function get(string $domain, string $path = '', string $name = null): array;

    public function getAll(): array;

    public function store(Cookie $cookie);

    public function remove(Cookie $cookie);

    public function removeAll();
}
