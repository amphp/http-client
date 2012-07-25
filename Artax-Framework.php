<?php
/**
 * Artax Framework Bootstrap File
 * 
 * @category    Artax
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */

use Artax\Http\StatusCodes,
    Artax\Http\StdRequestFactory,
    Artax\Http\StdResponse,
    Artax\Events\Mediator,
    Artax\Framework\Config\ConfigFactory,
    Artax\Framework\Routing\ObservableRoutePool,
    Artax\Framework\Routing\ObservableRouteFactory,
    Artax\Framework\Routing\ObservableResourceFactory,
    Artax\Framework\Routing\ClassResourceMapper,
    Artax\Framework\Routing\BadResourceMethodException,
    Artax\Framework\Http\Exceptions\NotFoundException,
    Artax\Framework\Http\Exceptions\MethodNotAllowedException,
    Artax\Framework\Http\Exceptions\HttpStatusException,
    Artax\Framework\UnifiedErrorHandler,
    Artax\Framework\Events\SystemEventDeltaException,
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

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', false);

if (!defined('ARTAX_DEBUG_MODE')) {
    define('ARTAX_DEBUG_MODE', 0);
}

$handler = new UnifiedErrorHandler(new StdResponse($mediator), $mediator, ARTAX_DEBUG_MODE);
$handler->register();


/*
 * -------------------------------------------------------------------------------------------------
 * Define injection parameters for system classes that typehint interfaces.
 * -------------------------------------------------------------------------------------------------
 */

$mediatorDefinition = array('mediator' => $mediator);
$httpStatusHandlerDefinition = array(
    'mediator' => $mediator,
    'request'  => 'Artax\\Http\\StdRequest',
    'response' => 'Artax\\Framework\\Http\\ObservableResponse'
);
$http500HandlerDefinition = array(
    'mediator' => $mediator,
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
    'Artax\\Framework\\Http\\StatusHandlers\\HttpGeneral' => $httpStatusHandlerDefinition
));


/*
 * -------------------------------------------------------------------------------------------------
 * Load user-defined configuration.
 * -------------------------------------------------------------------------------------------------
 */

if (!defined('ARTAX_CONFIG_FILE')) {
    die('The ARTAX_CONFIG_FILE constant must be defined before continuing' . PHP_EOL);
}

$cfgParserFactory = new ConfigParserFactory();
$cfgParser = $cfgParserFactory->make(ARTAX_CONFIG_FILE);
$cfg = new Config();
$cfg->populate($cfgParser->parse(ARTAX_CONFIG_FILE));

$injector->share('Artax\\Framework\\Config\\Config', $cfg);

if ($cfg->has('bootstrapFile') && $bootstrapFile = $cfg->get('bootstrapFile')) {
    $userBootstrap = function($injector, $mediator, $bootstrapFile) {
        require $bootstrapFile;
    };
    $userBootstrap($injector, $mediator, $bootstrapFile);
}

if ($cfg->has('applyRouteShortcuts') && $cfg->get('applyRouteShortcuts')) {
    $injector->share('Artax\\Framework\\Plugins\\RouteShortcuts');
    $mediator->push('__sys.route.new', 'Artax\\Framework\\Plugins\\RouteShortcuts');
}
if ($cfg->has('autoResponseContentLength') && $cfg->get('autoResponseContentLength')) {
    $mediator->push('__sys.response.beforeSend', 'Artax\\Framework\\Plugins\\AutoResponseContentLength');
}
if ($cfg->has('autoResponseDate') && $cfg->get('autoResponseDate')) {
    $mediator->push('__sys.response.beforeSend', 'Artax\\Framework\\Plugins\\AutoResponseDate');
}
if ($cfg->has('autoResponseStatus') && $cfg->get('autoResponseStatus')) {
    $mediator->push('__sys.response.beforeSend', 'Artax\\Framework\\Plugins\\AutoResponseStatus');
}
/*
if ($cfg->has('autoResponseEncode') && $cfg->get('autoResponseEncode')) {
    $mediator->push('__sys.response.beforeSend', 'Artax\\Framework\\Plugins\\AutoResponseEncode');
    $injector->define('Artax\\Framework\\Plugins\\AutoResponseEncode',
        array('request' => 'Artax\\Http\\FormEncodableRequest')
    );
}
*/
if ($cfg->has('eventListeners')) {
    $mediator->pushAll($cfg->get('eventListeners'));
}

if ($cfg->has('injectionDefinitions')) {
    $injector->defineAll($cfg->get('injectionDefinitions'));
}

if ($cfg->has('interfaceImplementations')) {
    $injector->implementAll($cfg->get('interfaceImplementations'));
}


/*
 * -------------------------------------------------------------------------------------------------
 * Prevent post-boot changes to protected listeners.
 * -------------------------------------------------------------------------------------------------
 */

$mediator->push('__sys.http-404', 'Artax\\Framework\\Http\\StatusHandlers\\Http404');
$mediator->push('__sys.http-405', 'Artax\\Framework\\Http\\StatusHandlers\\Http405');
$mediator->push('__sys.http-406', 'Artax\\Framework\\Http\\StatusHandlers\\Http406');
$mediator->push('__sys.exception', 'Artax\\Framework\\Http\\StatusHandlers\\Http500');

$mediator->unshift('__mediator.delta', function(Mediator $mediator) {
    list($eventName, $deltaType) = $mediator->getLastQueueDelta();
    if (strpos($eventName, '__') === 0) {
        throw new SystemEventDeltaException(
            "Protected event listener `$eventName` may not be modified after boot"
        );
    }
});


/*
 * -------------------------------------------------------------------------------------------------
 * Create a request, router and route pool; register routes if user listeners haven't already.
 * -------------------------------------------------------------------------------------------------
 */

$requestFactory = new StdRequestFactory;
$request = $requestFactory->make($_SERVER);
$injector->share('Artax\\Http\\StdRequest', $request);

$router = $injector->make('Artax\\Framework\\Routing\\ObservableRouter');
$routePool = new ObservableRoutePool($mediator, new ObservableRouteFactory($mediator));
if (!count($routePool)) {
    $routePool->addAllRoutes($cfg->get('routes'));
}


/*
 * -------------------------------------------------------------------------------------------------
 * Route the request and invoke the resulting resource.
 * -------------------------------------------------------------------------------------------------
 */

$mediator->notify('__sys.ready');

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
    
    $availableMethods = array_intersect($e->getAvailableMethods(),
        array('options', 'get', 'head', 'post', 'put', 'delete', 'trace', 'connect')
    );
    $mediator->notify('__sys.http-' . StatusCodes::HTTP_METHOD_NOT_ALLOWED,
        new MethodNotAllowedException($availableMethods)
    );
    
} catch (NotAcceptableException $e) {
    
    $mediator->notify('__sys.http-' . StatusCodes::HTTP_NOT_ACCEPTABLE, $e);

} catch (HttpStatusException $e) {
    
    $mediator->notify('__sys.http-general', $e);
    
}
