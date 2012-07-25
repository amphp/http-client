<?php

/**
 * Integration Testing: WebApp config file
 */

$cfg = new StdClass;

$cfg->bootstrapFile = __DIR__ . '/web-app-bootstrap.php';

$cfg->routes = array(
    '/'            => 'WebApp\\Resources\\Index',
    '/error'       => 'WebApp\\Resources\\Error',
    '/exception'   => 'WebApp\\Resources\\ExceptionTest',
    '/fatal-error' => 'WebApp\\Resources\\FatalError',
    '/post-only'   => 'WebApp\\Resources\\PostOnly',
    '/sysevent'    => 'WebApp\\Resources\\IllegalSysEventDelta',
    '/auto-status' => 'WebApp\\Resources\\PluginAutoStatus',
    '/auto-length' => 'WebApp\\Resources\\PluginAutoContentLength'
);

$cfg->eventListeners = array(
    'app.http-404' => 'WebApp\\HttpHandlers\\Http404',
    'app.http-405' => 'WebApp\\HttpHandlers\\Http405',
    'app.http-500' => 'WebApp\\HttpHandlers\\Http500'
);

$cfg->interfaceImplementations = array(
    'Artax\\Events\\Mediator' => 'Artax\\Framework\\Events\\ProvisionedNotifier'
);

$cfg->applyRouteShortcuts = true;

$cfg->autoResponseContentLength = true;
$cfg->autoResponseDate = true;
$cfg->autoResponseStatus = true;

$cfg->autoResponseEncode = false;
