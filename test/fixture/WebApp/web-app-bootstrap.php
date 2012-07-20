<?php

/**
 * Integration Testing: WebApp bootstrap file
 */

spl_autoload_register(function($className) {
    if (0 === strpos($className, 'WebApp\\')) {
        $className = str_replace('\\', '/', $className);
        require __DIR__ . "/src/$className.php";
    }
});
