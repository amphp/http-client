<?php

abstract class BaseEncodingTest extends PHPUnit_Framework_TestCase {
    
    public function setUp() {
        if (!extension_loaded('zlib')) {
            $this->markTestSkipped('zlib extension required for Encoding package tests');
        } elseif (!function_exists('gzdecode')
            || !function_exists('gzencode')
            || !function_exists('gzinflate')
            || !function_exists('gzdeflate')
        ) {
            $this->markTestSkipped('zlib extension required for Encoding package tests');
        }
    }
    
}
