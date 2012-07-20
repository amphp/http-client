<?php

/**
 * Integration Testing: WebApp config file
 */

$cfg = new StdClass;
$cfg->bootstrapFile = WEB_APP_SYSTEM_DIR . '/web-app-bootstrap.php';
$cfg->routes = array(
    '/' => 'WebApp\\Resources\\Index',
    '/test' => 'WebApp\\Resources\\Test',
    '/error' => 'WebApp\\Resources\\Error',
);
