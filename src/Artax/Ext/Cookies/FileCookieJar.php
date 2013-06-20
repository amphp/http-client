<?php

namespace Artax\Ext\Cookies;

class FileCookieJar extends ArrayCookieJar {
    
    private $storagePath;
    
    function __construct($storagePath) {
        
        if (!file_exists($storagePath)) {
            $cookieFileHandle = $this->createStorageFile($storagePath);
        } elseif (FALSE === ($cookieFileHandle = @fopen($storagePath, 'r+'))) {
            throw new \RuntimeException(
                'Failed opening cookie storage file for reading: ' . $storagePath
            );
        }
        
        while (!feof($cookieFileHandle)) {
            if ($line = fgets($cookieFileHandle)) {
                $cookie = Cookie::fromString($line);
                $this->store($cookie);
            }
        }
        
        $this->storagePath = $storagePath;
    }
    
    private function createStorageFile($storagePath) {
        $dir = dirname($storagePath);
        if (!is_dir($dir)) {
            $this->createStorageDirectory($dir);
        }
        
        if (!$cookieFileHandle = @fopen($storagePath, 'w+')) {
            throw new \RuntimeException(
                'Failed reading cookie storage file: ' . $storagePath
            );
        }
        
        return $cookieFileHandle;
    }
    
    private function createStorageDirectory($dir) {
        if (!@mkdir($dir, 0777, TRUE)) {
            throw new \RuntimeException(
                'Failed creating cookie storage directory: ' . $dir
            );
        }
    }
    
    function __destruct() {
        $cookieData = '';
        
        foreach ($this->getAll() as $domain => $pathArr) {
            foreach ($pathArr as $path => $cookieArr) {
                foreach ($cookieArr as $name => $cookie) {
                    if (!$cookie->isExpired()) {
                        $cookieData .= $cookie . PHP_EOL;
                    }
                }
            }
        }
        
        file_put_contents($this->storagePath, $cookieData);
    }
}

