<?php

/**
 * Artax Provider Class File
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    ${license.txt}
 * @version    ${project.version}
 */

namespace Artax;
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
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
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
     * @var ReflectionPool
     */
    private $reflectionPool;
    
    /**
     * Constructor
     * 
     * @param ReflectionPool $reflectionPool
     * @return void
     */
    public function __construct(ReflectionPool $reflectionPool) {
        $this->reflectionPool = $reflectionPool;
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
    public function define($class, array $definition) {
        $lowClass = strtolower($class);
        $this->injectionDefinitions[$lowClass] = $definition;
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
     * Stores a shared instance for the specified class
     * 
     * If an instance of the class is specified, it will be stored and shared
     * for calls to `Provider::make` for that class until the shared instance
     * is manually removed or refreshed.
     * 
     * If no object instance is specified, the Provider will mark the class
     * as "shared" and the next time the Provider is used to instantiate the
     * class it's instance will be stored and shared.
     * 
     * @param string $class Name of the class to share
     * @param mixed  $instance   An instance of the shared class
     * 
     * @return void
     * @throws InvalidArgumentException
     */
    public function share($class, $instance = null) {
        $lowClass = strtolower($class);
        
        if (!$instance) {
            $this->sharedClasses[$lowClass] = null;
        } elseif ($instance instanceof $class) {
            $this->sharedClasses[$lowClass] = $instance;
        } else {
            $type = is_object($instance) ? get_class($instance) : gettype($instance);
            throw new InvalidArgumentException(
                get_class($this).'::share() argument 2 must be an '
                ."instance of $class: $type provided"
            );
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
            $ctorParams = $this->reflectionPool->getConstructorParameters($className);
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
            
            $reflClass = $this->reflectionPool->getClass($className);
            
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
                
                $instanceArgs[] = is_string($definition[$paramName])
                    ? $this->make($definition[$paramName])
                    : $definition[$paramName];
                
                continue;
            }
            
            $reflectedParam = $reflectedCtorParams[$i];
            $typehint = $this->reflectionPool->getTypehint($reflectedParam);
            
            if ($typehint && $this->isInstantiable($typehint)) {
                $instanceArgs[] = $this->make($typehint);
            } elseif ($typehint && $this->isImplemented($typehint)) {
                $instanceArgs[] = $this->make($this->getImplementation($typehint));
            } elseif ($reflectedParam->isDefaultValueAvailable()
                && null === $reflectedParam->getDefaultValue()
            ) {
                $instanceArgs[] = null;
            } elseif ($typehint) {
                throw new ProviderDefinitionException(
                    'Injection definition required for non-concrete parameter $'.
                    "$paramName of type `$typehint` at argument " . ($i+1)
                );
            } else {
                throw new ProviderDefinitionException(
                    'Scalar default value not allowed at argument ' . ($i+1)
                );
            }
        }
        
        return $instanceArgs;
    }
    
    /**
     * @param string $className
     * @return bool
     */
    private function isInstantiable($className) {
        return $this->reflectionPool->getClass($className)->isInstantiable();
    }
    
}
