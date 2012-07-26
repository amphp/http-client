<?php

/**
 * Integration Testing: WebApp config file
 */

$cfg = new StdClass;

$autoloader = function() {
    spl_autoload_register(function($className) {
        if (0 === strpos($className, 'WebApp\\')) {
            $className = str_replace('\\', '/', $className);
            require __DIR__ . "/src/$className.php";
        }
    });
};

$cfg->applyRouteShortcuts = true;
$cfg->autoResponseContentLength = true;
$cfg->autoResponseDate = true;
$cfg->autoResponseStatus = true;
$cfg->autoResponseEncode = false;

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
    '__sys.ready'  => $autoloader,
    'app.http-404' => 'WebApp\\HttpHandlers\\Http404',
    'app.http-405' => 'WebApp\\HttpHandlers\\Http405',
    'app.http-500' => 'WebApp\\HttpHandlers\\Http500'
);

$cfg->injectionImplementations = array(
    'Artax\\Events\\Mediator' => 'Artax\\Framework\\Events\\ProvisionedNotifier'
);
