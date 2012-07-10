<?php

define('ARTAX_SYSDIR', dirname(__DIR__));

spl_autoload_register(function($cls) {
    if (0 === strpos($cls, 'Artax\\')) {
        $cls = str_replace('\\', '/', $cls);        
        require ARTAX_SYSDIR . "/src/$cls.php";
    }
});

