<?php

abstract class BaseEncodingTest extends PHPUnit_Framework_TestCase {
    
    public function setUp() {
        if (!extension_loaded('zlib')) {
            $this->markTestSkipped('zlib extension required for Encoding packag tests');
        }
    }
    
}
