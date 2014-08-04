<?php

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(function($class) {
    if (strpos($class, 'ArtaxTest\\') === 0) {
        $name = substr($class, strlen('Artax\\Test'));
        $file = __DIR__ . '/' . strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});
