<?php
/**
 * Artax Framework Bootstrap File
 * 
 * To use, specify a valid Artax configuration file and require
 * the Artax-Framework.php bootstrap file in your front controller:
 * 
 * ```php
 * define('ARTAX_DEBUG_MODE', 1); // Optional debug mode flag (turned off by default)
 * define('ARTAX_CONFIG_FILE', '/hard/path/to/config.php');
 * require '/hard/path/to/Artax-Framework.php';
 * ```
 * 
 * @category    Artax
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */

use Artax\Http\StatusCodes,
    Artax\Http\StdResponse,
    Artax\Http\FormEncodableRequest,
    Artax\Http\SuperglobalRequestDetector,
    Artax\Events\Mediator,
    Artax\Framework\UnifiedErrorHandler,
    Artax\Framework\Configuration\AppConfig,
    Artax\Framework\Configuration\Configurator,
    Artax\Framework\Configuration\Parsers\PhpConfigParser,
    Artax\Framework\Configuration\PluginLoader,
    Artax\Framework\Configuration\PluginManifest,
    Artax\Framework\Configuration\PluginManifestFactory,
    Artax\Framework\Events\ProvisionedNotifier,
    Artax\Framework\Http\Exceptions\HttpStatusException,
    Artax\Framework\Http\Exceptions\MethodNotAllowedException,
    Artax\Framework\Http\Exceptions\NotFoundException,
    Artax\Framework\Events\SystemEventDeltaException,
    Artax\Framework\Routing\BadResourceMethodException,
    Artax\Framework\Routing\ClassResourceMapper,
    Artax\Framework\Routing\ObservableResourceFactory,
    Artax\Framework\Routing\ObservableRouteFactory,
    Artax\Framework\Routing\ObservableRoutePool,
    Artax\Injection\Provider,
    Artax\Injection\ReflectionPool,
    Artax\Negotiation\NotAcceptableException;


define('ARTAX_SYSTEM_VERSION', 0);
require __DIR__ . '/Artax.php';


/*
 * -------------------------------------------------------------------------------------------------
 * Detect the client request; instantiate the injection container; instantiate the event mediator.
 * -------------------------------------------------------------------------------------------------
 */

try {
    $reqDetector = new SuperglobalRequestDetector();
    
    $request = new FormEncodableRequest(
        $reqDetector->detectUri($_SERVER),
        $reqDetector->detectMethod($_SERVER)
    );
    
    $request->setAllHeaders($reqDetector->detectHeaders($_SERVER));
    
    if ($request->allowsEntityBody()) {
        $request->setBody($reqDetector->detectBody());
    }
    
    $request->setHttpVersion($reqDetector->detectHttpVersion($_SERVER));
    
} catch (Exception $e) {
    die('URI detection failed: cannot continue');
}

$reflPool = new ReflectionPool;
$injector = new Provider($reflPool);
$mediator = new ProvisionedNotifier($injector);

$injector->share($mediator);
$injector->share($reflPool);
$injector->share($request);


/*
 * -------------------------------------------------------------------------------------------------
 * Define the error handling environment; register error, exception and shutdown handlers.
 * -------------------------------------------------------------------------------------------------
 */

if (!defined('ARTAX_DEBUG_MODE')) {
    define('ARTAX_DEBUG_MODE', 0);
}

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', false);

$unifiedHandler = new UnifiedErrorHandler(new StdResponse, $mediator, ARTAX_DEBUG_MODE);
$unifiedHandler->register();


/*
 * -------------------------------------------------------------------------------------------------
 * Define system class injection parameters.
 * -------------------------------------------------------------------------------------------------
 */

$mediatorDefinition = array(
    ':mediator' => $mediator
);
$httpStatusHandlerDefinition = array(
    ':mediator' => $mediator,
    'request'  => 'Artax\\Http\\FormEncodableRequest',
    'response' => 'Artax\\Framework\\Http\\ObservableResponse'
);
$http500HandlerDefinition = array(
    ':mediator' => $mediator,
    'request'  => 'Artax\\Http\\FormEncodableRequest',
    'response' => 'Artax\\Http\\StdResponse'
);

$observableRoutePoolDefinition = array(
    ':mediator' => $mediator,
    'routeFactory' => 'Artax\\Framework\\Routing\\ObservableRouteFactory'
);

$injector->defineAll(array(
    'Artax\\Framework\\Http\\ObservableResponse' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableResource' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableResourceFactory' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRoute' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRouteFactory' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRoutePool' => $observableRoutePoolDefinition,
    'Artax\\Framework\\Routing\\ObservableRouter' => $mediatorDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http404' => $httpStatusHandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http405' => $httpStatusHandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http406' => $httpStatusHandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http500' => $http500HandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\HttpGeneral' => $httpStatusHandlerDefinition,
));

$injector->implementAll(array(
    'Artax\\Routing\\RouteMatcher' => 'Artax\\Framework\\Routing\\ObservableRouter',
    'Artax\\Routing\\RouteStorage' => 'Artax\\Framework\\Routing\\ObservableRoutePool'
));


/*
 * -------------------------------------------------------------------------------------------------
 * Load user-defined configuration.
 * -------------------------------------------------------------------------------------------------
 */

if (!defined('ARTAX_CONFIG_FILE')) {
    die('The ARTAX_CONFIG_FILE constant must be defined to continue' . PHP_EOL);
}

$configParser = new PhpConfigParser();
$appConfig = new AppConfig();
$appConfig->populate($configParser->parse(ARTAX_CONFIG_FILE));

$injector->share($appConfig);

$configurator = new Configurator($injector, $mediator);
$configurator->apply($appConfig);


/*
 * -------------------------------------------------------------------------------------------------
 * Load plugins.
 * -------------------------------------------------------------------------------------------------
 */

if ($appConfig->has('plugins')) {
    
    if (!($appConfig->has('pluginDir') && $pluginDir = $appConfig->get('pluginDir'))) {
        $pluginDir = __DIR__ . '/plugins';
    }
    
    $pluginLoader = new PluginLoader(
        $configurator,
        $configParser,
        new PluginManifestFactory,
        $pluginDir,
        ARTAX_SYSTEM_VERSION
    );
    
    $pluginLoader->load($appConfig->get('plugins'));
}


/*
 * -------------------------------------------------------------------------------------------------
 * Prevent changes to protected system event queues.
 * -------------------------------------------------------------------------------------------------
 */

$mediator->unshift('__sys.http-404', 'Artax\\Framework\\Http\\StatusHandlers\\Http404');
$mediator->unshift('__sys.http-405', 'Artax\\Framework\\Http\\StatusHandlers\\Http405');
$mediator->unshift('__sys.http-406', 'Artax\\Framework\\Http\\StatusHandlers\\Http406');
$mediator->unshift('__sys.exception', 'Artax\\Framework\\Http\\StatusHandlers\\Http500');

$mediator->unshift('__mediator.delta', function(Mediator $mediator) {
    list($eventName, $deltaType) = $mediator->getLastQueueDelta();
    $protectedQueues = array(
        '__sys.http-404',
        '__sys.http-405',
        '__sys.http-406',
        '__sys.exception',
        '__mediator.delta'
    );
    if (in_array($eventName, $protectedQueues)) {
        throw new SystemEventDeltaException(
            "Protected event listener queue may not be modified after boot: $eventName"
        );
    }
});


/*
 * -------------------------------------------------------------------------------------------------
 * Route the request and invoke the resulting resource.
 * -------------------------------------------------------------------------------------------------
 */

$mediator->notify('__sys.ready');

$router = $injector->make('Artax\\Routing\\RouteMatcher');
$routePool = $injector->make('Artax\\Routing\\RouteStorage');

if (!count($routePool)) {
    $routePool->addAllRoutes($appConfig->get('routes'));
}

try {
    
    if (!$router->match($request->getPath(), $routePool)) {
        throw new NotFoundException;
    } else {
        $resourceClass = $router->getMatchedResource();
        $resourceMethod = strtolower($request->getMethod());
        $routeArgs = $router->getMatchedArgs();
        
        $resourceFactory = new ObservableResourceFactory($mediator);
        $resourceMapper = new ClassResourceMapper($injector, $reflPool, $resourceFactory);
        $resource = $resourceMapper->make($resourceClass, $resourceMethod, $routeArgs);
        $resource();
    }
    
} catch (NotFoundException $e) {
    
    $mediator->notify('__sys.http-' . StatusCodes::HTTP_NOT_FOUND, $e);
    
} catch (BadResourceMethodException $e) {
    
    $mediator->notify('__sys.http-' . StatusCodes::HTTP_METHOD_NOT_ALLOWED,
        new MethodNotAllowedException($e->getAvailableMethods())
    );
    
} catch (NotAcceptableException $e) {
    
    $mediator->notify('__sys.http-' . StatusCodes::HTTP_NOT_ACCEPTABLE, $e);

} catch (HttpStatusException $e) {
    
    $mediator->notify('__sys.http-general', $e);
    
}
