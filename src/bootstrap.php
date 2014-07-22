<?php

spl_autoload_register(function($class) {
    if (strpos($class, 'Artax\\') === 0) {
        $name = substr($class, strlen('Artax'));
        require __DIR__ . "/../lib" . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
    }
});

require __DIR__ . '/../vendor/Alert/src/bootstrap.php';
require __DIR__ . '/../vendor/Addr/src/bootstrap.php';
require __DIR__ . '/../vendor/After/src/bootstrap.php';
