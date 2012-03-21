<?php

error_reporting(E_ALL);
ini_set('memory_limit', '1024M');
define('AX_SYSTEM_DIR', dirname(dirname(realpath(__FILE__))));

/**
 * Use PSR-0 autoloader for autoloading libs for testing
 */
require AX_SYSTEM_DIR . '/test/SplClassLoader.php';
(new SplClassLoader('Artax', AX_SYSTEM_DIR . '/src'))->register();

/**
 * Test helper traits
 */
require AX_SYSTEM_DIR . '/test/MagicTestGetTrait.php';
require AX_SYSTEM_DIR . '/test/UsesErrorExceptionsTrait.php';

