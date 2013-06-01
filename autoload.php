<?php

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/src/$class.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Amp\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/vendor/Amp/src/$class.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});

