<?php

/**
 * Unit Testing Bootstrap File
 * 
 * @category   Artax
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

define('ARTAX_SYSTEM_DIR', dirname(__DIR__));

/*
 * --------------------------------------------------------------------
 * REGISTER ARTAX LIB AUTOLOADER
 * --------------------------------------------------------------------
 */

spl_autoload_register(function($cls) {
    if (0 === strpos($cls, 'Artax\\')) {
        $cls = str_replace('\\', '/', $cls);        
        require ARTAX_SYSTEM_DIR . "/src/$cls.php";
    }
});

