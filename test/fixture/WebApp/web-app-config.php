<?php

/**
 * Integration Testing: WebApp config file
 */

$cfg = new StdClass;

$cfg->requiredFiles = array(
    __DIR__ . '/web-app-bootstrap.php'
);

$cfg->plugins = array(
    'ArtaxPlugins/RouteShortcuts'        => true,
    'ArtaxPlugins/ResponseContentLength' => 1,
    'ArtaxPlugins/ResponseStatus'        => 'yes',
    'ArtaxPlugins/ResponseDate'          => true,
    'ArtaxPlugins/ResponseEncode'        => false,
    'SomeNonexistentPlugin'              => 'no'
);

$cfg->routes = array(
    '/'            => 'WebApp\\Resources\\Index',
    '/error'       => 'WebApp\\Resources\\Error',
    '/exception'   => 'WebApp\\Resources\\ExceptionTest',
    '/fatal-error' => 'WebApp\\Resources\\FatalError',
    '/post-only'   => 'WebApp\\Resources\\PostOnly',
    '/post-redir'  => 'WebApp\\Resources\\PostRedirect',
    '/sysevent'    => 'WebApp\\Resources\\IllegalSysEventDelta',
    '/auto-status' => 'WebApp\\Resources\\PluginAutoStatus',
    '/auto-length' => 'WebApp\\Resources\\PluginAutoContentLength'
);

$cfg->eventListeners = array(
    'app.http-404' => 'WebApp\\HttpHandlers\\Http404',
    'app.http-405' => 'WebApp\\HttpHandlers\\Http405',
    'app.http-500' => 'WebApp\\HttpHandlers\\Http500'
);

$cfg->injectionImplementations = array(
    'Artax\\Events\\Mediator' => 'Artax\\Framework\\Events\\ProvisionedNotifier'
);
