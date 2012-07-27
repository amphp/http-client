<?php
/**
 * Provider Class File
 * 
 * @category    Artax
 * @package     Injection
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Injection;

use InvalidArgumentException,
    OutOfBoundsException,
    ReflectionClass,
    ReflectionException,
    ArrayAccess,
    Traversable,
    StdClass;
  
/**
 * A dependency injection container
 * 
 * @category    Artax
 * @package     Injection
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class Provider implements Injector {
    
    /**
     * @var array
     */
    private $injectionDefinitions = array();
    
    /**
     * @var array
     */
    private $nonConcreteimplementations = array();
    
    /**
     * @var array
     */
    private $sharedClasses = array();
    
    /**
     * @var ReflectionStorage
     */
    private $reflectionStorage;
    
    /**
     * @param ReflectionStorage $reflectionStorage
     * @return void
     */
    public function __construct(ReflectionStorage $reflectionStorage) {
        $this->reflectionStorage = $reflectionStorage;
    }
    
    /**
     * Instantiate a class subject to a predefined or call-time injection definition
     * 
     * @param string $class Class name
     * @param array  $customDefinition An optional array of custom instantiation parameters
     * 
     * @return mixed A dependency-injected object
     * @throws ProviderDefinitionException
     */
    public function make($class, array $customDefinition = null) {
        $lowClass = strtolower($class);
        
        if (isset($this->sharedClasses[$lowClass])) {
            return $this->sharedClasses[$lowClass];
        }
        
        if (null !== $customDefinition) {
            $definition = $customDefinition;
        } elseif ($this->isDefined($class)) {
            $definition = $this->injectionDefinitions[$lowClass];
        } else {
            $definition = array();
        }
        
        $obj = $this->getInjectedInstance($class, $definition);
        
        if ($this->isShared($lowClass)) {
            $this->sharedClasses[$lowClass] = $obj;
        }
        
        return $obj;
    }
    
    /**
     * Defines a custom injection definition for the specified class
     * 
     * @param string $class      Class name
     * @param mixed  $definition An associative array matching constructor
     *                           parameters to custom values
     * 
     * @return void
     */
    public function define($className, array $injectionDefinition) {
        $this->validateInjectionDefinition($injectionDefinition);
        $lowClass = strtolower($className);
        $this->injectionDefinitions[$lowClass] = $injectionDefinition;
    }
    
    private function validateInjectionDefinition($injectionDefinition) {
        foreach ($injectionDefinition as $paramName => $value) {
            if (0 !== strpos($paramName, ':') && !is_string($value)) {
                throw new ProviderDefinitionException(
                    "Invalid injection definition for parameter `$paramName`; raw parameter " .
                    "names must be prefixed with `r:` (r:$paramName) to differentiate them " .
                    'from provisionable class names.'
                );
            }
        }
    }
    
    /**
     * Retrieves the custom definition for the specified class
     * 
     * @param string $className
     * 
     * @return array
     */
    public function getDefinition($className) {
        if (!$this->isDefined($className)) {
            throw new OutOfBoundsException("No definition specified for $className");
        }
        $lowClass = strtolower($className);
        return $this->injectionDefinitions[$lowClass];
    }
    
    /**
     * Determines if an injection definition exists for the given class name
     * 
     * @param string $class Class name
     * 
     * @return bool Returns true if a definition is stored or false otherwise
     */
    public function isDefined($class) {
        $lowClass = strtolower($class);
        return isset($this->injectionDefinitions[$lowClass]);
    }
    
    /**
     * Defines multiple injection definitions at one time
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass or Traversable
     * 
     * @return int Returns the number of definitions stored by the operation.
     */
    public function defineAll($iterable) {
        if (!($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable)
        ) {
            throw new InvalidArgumentException(
                get_class($this) . '::defineAll expects an array, StdClass or '
                .'Traversable object at Argument 1'
            );
        }
        
        $added = 0;
        foreach ($iterable as $class => $definition) {
            $this->define($class, $definition);
            ++$added;
        }
        
        return $added;
    }
    
    /**
     * Clear a previously defined injection definition
     * 
     * @param string $class Class name
     * 
     * @return void
     */
    public function clearDefinition($class) {
        $lowClass = strtolower($class);
        unset($this->injectionDefinitions[$lowClass]);
    }
    
    /**
     * Clear all injection definitions from the container
     * 
     * @return void
     */
    public function clearAllDefinitions() {
        $this->injectionDefinitions = array();
    }
    
    /**
     * Defines an implementation class for all occurrences of a given interface or abstract
     * 
     * @param string $nonConcreteType
     * @param string $className
     * 
     * @return void
     */
    public function implement($nonConcreteType, $className) {
        $lowNonConcrete = strtolower($nonConcreteType);
        $this->nonConcreteimplementations[$lowNonConcrete] = $className;
    }
    
    /**
     * Retrive the assigned implementation class for the non-concrete type
     * 
     * @param string $nonConcreteType
     * 
     * @return string Returns the concrete class implementation name
     * @throws OutOfBoundsException
     */
    public function getImplementation($nonConcreteType) {
        if (!$this->isImplemented($nonConcreteType)) {
            throw new OutOfBoundsException(
                "The non-concrete typehint $nonConcreteType has no assigned implementation"
            );
        }
        $lowNonConcrete = strtolower($nonConcreteType);
        return $this->nonConcreteimplementations[$lowNonConcrete];
    }
    
    /**
     * Determines if an implementation is specified for the non-concrete type
     * 
     * @param string $nonConcreteType
     * 
     * @return bool
     */
    public function isImplemented($nonConcreteType) {
        $lowNonConcrete = strtolower($nonConcreteType);
        return isset($this->nonConcreteimplementations[$lowNonConcrete]);
    }
    
    /**
     * Defines multiple type implementations at one time
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass or Traversable
     * 
     * @return int Returns the number of implementations stored by the operation.
     */
    public function implementAll($iterable) {
        if (!($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable)
        ) {
            throw new InvalidArgumentException(
                get_class($this) . '::implementAll expects an array, StdClass or '
                .'Traversable object at Argument 1'
            );
        }
        
        $added = 0;
        foreach ($iterable as $nonConcreteType => $implementationClass) {
            $this->implement($nonConcreteType, $implementationClass);
            ++$added;
        }
        
        return $added;
    }
    
    /**
     * Clears an existing implementation definition for the non-concrete type
     * 
     * @param string $nonConcreteType
     * 
     * @return void
     */
    public function clearImplementation($nonConcreteType) {
        $lowNonConcrete = strtolower($nonConcreteType);
        unset($this->nonConcreteimplementations[$lowNonConcrete]);
    }
    
    /**
     * Clears an existing implementation definition for the non-concrete type
     * 
     * @param string $nonConcreteType
     * 
     * @return void
     */
    public function clearAllImplementations() {
        $this->nonConcreteimplementations = array();
    }
    
    /**
     * Stores a shared instance of the specified class
     * 
     * If an instance of the class is specified, it will be stored and shared
     * for calls to `Provider::make` for that class until the shared instance
     * is manually removed or refreshed.
     * 
     * If a string class name is specified, the Provider will mark the class
     * as "shared" and the next time the Provider is used to instantiate the
     * class it's instance will be stored and shared.
     * 
     * @param mixed $classNameOrInstance
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    public function share($classNameOrInstance) {
        if (is_string($classNameOrInstance)) {
            $lowClass = strtolower($classNameOrInstance);
            $this->sharedClasses[$lowClass] = null;
        } elseif (is_object($classNameOrInstance)) {
            $lowClass = strtolower(get_class($classNameOrInstance));
            $this->sharedClasses[$lowClass] = $classNameOrInstance;
        } else {
            $parameterType = gettype($classNameOrInstance);
            throw new InvalidArgumentException(
                get_class($this).'::share() requires a string class name or object instance at ' .
                "Argument 1; $parameterType specified"
            );
        }
    }
    
    /**
     * Shares all specified classes/instances
     * 
     * @param mixed $arrayOrTraversable
     * @return void
     * @throws InvalidArgumentException
     */
    public function shareAll($arrayOrTraversable) {
        if (!(is_array($arrayOrTraversable) || $arrayOrTraversable instanceof Traversable)) {
            $type = is_object($arrayOrTraversable)
                ? get_class($arrayOrTraversable)
                : gettype($arrayOrTraversable);
            throw new InvalidArgumentException(
                get_class($this).'::shareAll() requires an array or Traversable object at ' .
                "Argument 1; $type specified"
            );
        }
        
        foreach ($arrayOrTraversable as $toBeShared) {
            $this->share($toBeShared);
        }
    }
    
    /**
     * Determines if a given class name is marked as shared
     * 
     * @param string $class Class name
     * 
     * @return bool Returns true if a shared instance is stored or false if not
     */
    public function isShared($class) {
        $lowClass = strtolower($class);
        return isset($this->sharedClasses[$lowClass])
            || array_key_exists($lowClass, $this->sharedClasses);
    }
    
    /**
     * Forces re-instantiation of a shared class the next time it's requested
     * 
     * @param string $class Class name
     * 
     * @return void
     */
    public function refresh($class) {
        $lowClass = strtolower($class);
        if (isset($this->sharedClasses[$lowClass])) {
            $this->sharedClasses[$lowClass] = null;
        }
    }
    
    /**
     * Unshares the specified class
     * 
     * @param string $class Class name
     * 
     * @return void
     */
    public function unshare($class) {
        $lowClass = strtolower($class);
        unset($this->sharedClasses[$lowClass]);
    }
    
    /**
     * @param string $className
     * @return mixed Returns a dependency-injected object
     * @throws ProviderDefinitionException
     */
    protected function getInjectedInstance($className, array $definition) {
        try {
            $ctorParams = $this->reflectionStorage->getConstructorParameters($className);
        } catch (ReflectionException $e) {
            throw new ProviderDefinitionException(
                "Provider instantiation failure: $className doesn't exist".
                ' and could not be found by any registered autoloaders.',
                null, $e
            );
        }
        
        if (!$ctorParams) {
            return new $className;
        } else {
        
            try {
                $args = $this->buildNewInstanceArgs($ctorParams, $definition);
            } catch (ProviderDefinitionException $e) {
                $msg = $e->getMessage() . " in $className::__construct";
                throw new ProviderDefinitionException($msg);
            }
            
            $reflClass = $this->reflectionStorage->getClass($className);
            
            return $reflClass->newInstanceArgs($args);
        }
    }
    
    /**
     * @return array
     * @throws ProviderDefinitionException 
     */
    private function buildNewInstanceArgs(array $reflectedCtorParams, array $definition) {
        $instanceArgs = array();
        
        for ($i=0; $i<count($reflectedCtorParams); $i++) {
            
            $paramName = $reflectedCtorParams[$i]->name;
            
            if (isset($definition[$paramName])) {
                $instanceArgs[] = $this->make($definition[$paramName]);
                continue;
            }
            
            $rawParamKey = ":$paramName";
            if (isset($definition[$rawParamKey])) {
                $instanceArgs[] = $definition[$rawParamKey];
                continue;
            }
            
            $reflectedParam = $reflectedCtorParams[$i];
            $typehint = $this->reflectionStorage->getTypehint($reflectedParam);
            
            if ($typehint && $this->isInstantiable($typehint)) {
                $instanceArgs[] = $this->make($typehint);
            } elseif ($typehint && $this->isImplemented($typehint)) {
                $instanceArgs[] = $this->make($this->getImplementation($typehint));
            } elseif ($reflectedParam->isDefaultValueAvailable()) {
                $instanceArgs[] = $reflectedParam->getDefaultValue();
            } elseif ($typehint) {
                throw new ProviderDefinitionException(
                    'Injection definition/implementation required for non-concrete constructor '.
                    "parameter \$$paramName of type $typehint at argument " . ($i+1)
                );
            } else {
                $instanceArgs[] = null;
            }
        }
        
        return $instanceArgs;
    }
    
    /**
     * @param string $className
     * @return bool
     */
    private function isInstantiable($className) {
        return $this->reflectionStorage->getClass($className)->isInstantiable();
    }
}
