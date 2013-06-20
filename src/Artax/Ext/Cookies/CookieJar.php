<?php

namespace Artax\Ext\Cookies;

interface CookieJar {
    
    function get($domain, $path = '', $name = NULL);
    function getAll();
    function store(Cookie $cookie);
    function remove(Cookie $cookie);
    function removeAll();
    
}

