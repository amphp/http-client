<?php

spl_autoload_register(function($cls) {
    if (0 === strpos($cls, 'Artax\\')) {
        $cls = str_replace('\\', '/', $cls);        
        require dirname(__DIR__) . "/src/$cls.php";
    }
});

