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
    Artax\Http\StdRequestFactory,
    Artax\Http\RequestDetector,
    Artax\Http\StdResponse,
    Artax\Events\Mediator,
    Artax\Framework\BootConfigurator,
    Artax\Framework\UnifiedErrorHandler,
    Artax\Framework\Config\Config,
    Artax\Framework\Config\ConfigParserFactory,
    Artax\Framework\Config\ConfigValidator,
    Artax\Framework\Http\Exceptions\HttpStatusException,
    Artax\Framework\Http\Exceptions\MethodNotAllowedException,
    Artax\Framework\Http\Exceptions\NotFoundException,
    Artax\Framework\Events\SystemEventDeltaException,
    Artax\Framework\Routing\BadResourceMethodException,
    Artax\Framework\Routing\ClassResourceMapper,
    Artax\Framework\Routing\ObservableResourceFactory,
    Artax\Framework\Routing\ObservableRouteFactory,
    Artax\Framework\Routing\ObservableRoutePool,
    Artax\Negotiation\NotAcceptableException;


require __DIR__ . '/Artax.php';


/*
 * -------------------------------------------------------------------------------------------------
 * Instantiate the injection container; instantiate/share the event mediator and reflection pool.
 * -------------------------------------------------------------------------------------------------
 */

$reflPool = new Artax\Injection\ReflectionPool;
$injector = new Artax\Injection\Provider($reflPool);
$mediator = new Artax\Framework\Events\ProvisionedNotifier($injector);

$injector->share('Artax\\Framework\\Events\\ProvisionedNotifier', $mediator);
$injector->share('Artax\\Injection\\ReflectionPool', $reflPool);


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
 * Define system class injection parameters
 * -------------------------------------------------------------------------------------------------
 */

$mediatorDefinition = array(
    ':mediator' => $mediator
);
$httpStatusHandlerDefinition = array(
    ':mediator' => $mediator,
    'request'  => 'Artax\\Http\\StdRequest',
    'response' => 'Artax\\Framework\\Http\\ObservableResponse'
);
$http500HandlerDefinition = array(
    ':mediator' => $mediator,
    'request'  => 'Artax\\Http\\StdRequest',
    'response' => 'Artax\\Http\\StdResponse'
);

$injector->defineAll(array(
    'Artax\\Framework\\Http\\ObservableResponse' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableResource' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableResourceFactory' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRoute' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRouteFactory' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRoutePool' => $mediatorDefinition,
    'Artax\\Framework\\Routing\\ObservableRouter' => $mediatorDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http404' => $httpStatusHandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http405' => $httpStatusHandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http406' => $httpStatusHandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\Http500' => $http500HandlerDefinition,
    'Artax\\Framework\\Http\\StatusHandlers\\HttpGeneral' => $httpStatusHandlerDefinition,
));


/*
 * -------------------------------------------------------------------------------------------------
 * Load user-defined configuration.
 * -------------------------------------------------------------------------------------------------
 */

if (!defined('ARTAX_CONFIG_FILE')) {
    die('The ARTAX_CONFIG_FILE constant must be defined before continuing' . PHP_EOL);
}

$configParserFactory = new ConfigParserFactory();
$configParser = $configParserFactory->make(ARTAX_CONFIG_FILE);
$config = new Config();
$config->populate($configParser->parse(ARTAX_CONFIG_FILE));
$configValidator = new ConfigValidator();
$configValidator->validate($config);

$injector->share('Artax\\Framework\\Config\\Config', $config);

$bootConfigurator = new BootConfigurator($injector, $mediator);
$bootConfigurator->configure($config);


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
 * Create and share a request; notify interested listeners that the system is ready.
 * -------------------------------------------------------------------------------------------------
 */

$requestFactory = new StdRequestFactory(new RequestDetector);
$request = $requestFactory->make($_SERVER);
$injector->share($request);

$mediator->notify('__sys.ready');


/*
 * -------------------------------------------------------------------------------------------------
 * Route the request and invoke the resulting resource.
 * -------------------------------------------------------------------------------------------------
 */

$router = $injector->make('Artax\\Framework\\Routing\\ObservableRouter');
$routePool = new ObservableRoutePool($mediator, new ObservableRouteFactory($mediator));
if (!count($routePool)) {
    $routePool->addAllRoutes($config->get('routes'));
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
