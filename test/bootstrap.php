<?php

if (!defined('ARTAX_SYSTEM_DIR')) {
    define('ARTAX_SYSTEM_DIR', dirname(__DIR__));
}

/*
 * --------------------------------------------------------------------
 * Register Artax autoloader.
 * --------------------------------------------------------------------
 */

spl_autoload_register(function($cls) {
    if (0 === strpos($cls, 'Artax\\')) {
        $cls = str_replace('\\', '/', $cls);        
        require ARTAX_SYSTEM_DIR . "/src/$cls.php";
    }
});

/*
 * -------------------------------------------------------------------------------------------------
 * Register a class loader for plugins.
 * -------------------------------------------------------------------------------------------------
 */

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'ArtaxPlugins\\')) {
        $classParts = explode('\\', $class);
        array_shift($classParts);
        $pluginName = array_shift($classParts);
        require ARTAX_SYSTEM_DIR . "/plugins/$pluginName/src/" . implode('/', $classParts) . '.php';
    }
});

/*
 * --------------------------------------------------------------------
 * Register vfsStream autoloader.
 * --------------------------------------------------------------------
 */

spl_autoload_register(function($cls) {
    if (0 === strpos($cls, 'org\\bovigo\\vfs\\')) {
        $cls = str_replace('\\', '/', $cls);        
        require ARTAX_SYSTEM_DIR . "/vendor/vfsStream/src/main/php/$cls.php";
    }
});

