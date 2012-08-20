<?php

/**
 * Integration Testing: WebApp config file
 */

$cfg = new StdClass;

$cfg->requiredFiles = array(
    __DIR__ . '/web-app-bootstrap.php'
);

$cfg->plugins = array(
    'Artax/RouteShortcuts'        => true,
    'Artax/ResponseStatus'        => true,
    'Artax/ResponseDate'          => true,
    'Artax/ResponseEncoder'       => true
);

$cfg->routes = array(
    '/'            => 'WebApp\\Resources\\Index',
    '/error'       => 'WebApp\\Resources\\Error',
    '/exception'   => 'WebApp\\Resources\\ExceptionTest',
    '/fatal-error' => 'WebApp\\Resources\\FatalError',
    '/post-only'   => 'WebApp\\Resources\\PostOnly',
    '/stream-body' => 'WebApp\\Resources\\StreamBody',
    '/post-redir'  => 'WebApp\\Resources\\PostRedirect',
    '/sysevent'    => 'WebApp\\Resources\\IllegalSysEventDelta',
    '/auto-status' => 'WebApp\\Resources\\PluginAutoStatus',
    '/auto-length' => 'WebApp\\Resources\\PluginAutoContentLength',
    '/negotiation' => 'WebApp\\Resources\\Negotiation',
    '/diagnostics' => 'WebApp\\Resources\\Diagnostics',
    '/get-redir'   => 'WebApp\\Resources\\GetRedirect',
    '/async'       => 'WebApp\\Resources\\AsyncTest'
);

$cfg->eventListeners = array(
    'app.http-404' => 'WebApp\\HttpHandlers\\Http404',
    'app.http-405' => 'WebApp\\HttpHandlers\\Http405',
    'app.http-500' => 'WebApp\\HttpHandlers\\Http500'
);

$cfg->injectionImplementations = array(
    'Artax\\Events\\Mediator' => 'Artax\\Framework\\Events\\ProvisionedNotifier'
);
