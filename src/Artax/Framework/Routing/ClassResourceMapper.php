<?php
/**
 * ClassResourceMapper Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Framework\Routing;

use ReflectionMethod,
    Artax\Injection\Injector,
    Artax\Injection\ReflectionStorage,
    Artax\Injection\ProviderDefinitionException,
    Artax\Framework\Routing\BadResourceMethodException;

/**
 * Generates callable resources from routing-matched values
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ClassResourceMapper {
    
    /**
     * @var Injector
     */
    private $injector;
    
    /**
     * @var ReflectionPool
     */
    private $reflectionStorage;
    
    /**
     * @var ObservableResourceFactory
     */
    private $resourceFactory;
    
    /**
     * @param Injector $injector
     * @param ReflectionStorage $reflectionStorage
     * @param ObservableResourceFactory $resourceFactory
     * @return void
     */
     public function __construct(
        Injector $injector,
        ReflectionStorage $reflectionStorage,
        ObservableResourceFactory $resourceFactory
    ) {
        $this->injector = $injector;
        $this->reflectionStorage = $reflectionStorage;
        $this->resourceFactory = $resourceFactory;
    }
    
    /**
     * Generate an invokable resource and accompanying parameters
     * 
     * @param string $resourceClass
     * @param string $resourceMethod
     * @param array $namedArgs
     * @return array
     * @throws BadResourceClassException
     * @throws BadResourceMethodException
     */
    public function make($resourceClass, $resourceMethod, array $namedMethodArgs) {
        $resource = $this->provisionResource($resourceClass);
        $callableResource = array($resource, $resourceMethod);
        
        if (is_callable($callableResource)) {
            $reflMethod = new ReflectionMethod($resourceClass, $resourceMethod);
            $mergedArgs = $this->mergeMethodArgs($reflMethod, $namedMethodArgs);
            return $this->resourceFactory->make($callableResource, $mergedArgs);
        }
        
        throw new BadResourceMethodException(get_class_methods($resource));
    }
    
    /**
     * @param string $className
     * @return mixed
     * @throws BadResourceClassException
     */
    private function provisionResource($className) {
        try {
            $instance = $this->injector->make($className);
        } catch (ProviderDefinitionException $e) {
            throw new BadResourceClassException(
                "Invalid resource class: $className could not be provisioned", null, $e
            );
        }
        return $instance;
    }
    
    /**
     * Generates resource method arguments from the route match
     * 
     * 1. If the parameter name matches a value in $namedMethodArgs, the
     * named value is used
     * 2. If the parameter has a default value, the default is used
     * 3. If the parameter is typehinted with a class name, the injection container
     * attempts to instantiate an instance of the specified class
     * 4. If none of the other requirements are met, a null value is passed
     * 
     * @param ReflectionMethod $reflMethod
     * @param array $namedMethodArgs
     * 
     * @return array
     */
    private function mergeMethodArgs(ReflectionMethod $reflMethod, array $namedMethodArgs) {
        $merged = array();
        $methodParameters = $reflMethod->getParameters();
        
        foreach ($methodParameters as $param) {
            $key = $param->getName();
            if (isset($namedMethodArgs[$key])) {
                $merged[$key] = $namedMethodArgs[$key];
            } elseif ($param->isDefaultValueAvailable()) {
                $merged[$key] = $param->getDefaultValue();
            } elseif ($typehint = $this->reflectionStorage->getTypehint($param)) {
                $merged[$key] = $this->injector->make($typehint);
            } else {
                $merged[$key] = null;
            }
        }
        
        return $merged;
    }
}
