<?php
/**
 * Artax Library Bootstrap File
 * 
 * Use this bootstrap file if you wish to include specific Artax packages in your
 * project without using the framework writ-large. Framework users SHOULD NOT
 * include this file and SHOULD INSTEAD use the `Artax-Framework.php` bootstrap
 * file (which references this file) located in the base project directory.
 * 
 * @category    Artax
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */

if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
    die('Artax requires PHP 5.3 or higher' . PHP_EOL);
}

if (extension_loaded('mbstring')) { 
    if( ini_get('mbstring.func_overload') & 2 ) { 
        throw new \RuntimeException('Artax requires that string functions not be overloaded by "mbstring.func_overload"');
    }
}

spl_autoload_register(function($className) {
    if (0 === strpos($className, 'Artax\\')) {
        $className = str_replace('\\', '/', $className);      
        require __DIR__ . "/src/$className.php";
    }
});
