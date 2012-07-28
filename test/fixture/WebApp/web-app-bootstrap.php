<?php

// Register any class autoloaders you need here. The Artax-Framework bootstrap registers its own
// autoloader for Artax classes, so don't bother registering a loader for the Artax namespace.
spl_autoload_register(function($class) {
    if (0 === strpos($class, 'WebApp\\')) {
        $class = str_replace('\\', '/', $class);
        require __DIR__ . "/src/$class.php";
    }
});
