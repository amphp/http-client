<?php

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        require __DIR__ . "/src/$class.php";
    }
});