<?php

/**
 * Integration Testing: front controller
 */

define('WEB_APP_SYSTEM_DIR', dirname(__DIR__));
define('ARTAX_DEBUG_LEVEL', 1);
define('ARTAX_CONFIG_FILE', WEB_APP_SYSTEM_DIR . '/web-app-config.php');

require dirname(dirname(dirname(WEB_APP_SYSTEM_DIR))) . '/Artax-Framework.php';
