<?php

/**
 * Artax Provider Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Core;
use InvalidArgumentException,
    ReflectionClass,
    ReflectionException,
    ArrayAccess,
    Traversable,
    StdClass;
  
/**
 * A dependency injection/provider class
 * 
 * The Provider is a dependency injection container existing specifically to
 * enable lazy instantiation of event listeners. `Provider::make` automatically
 * instantiates an instance of the given class name using reflection to
 * determine the class's constructor parameters. Non-concrete dependencies
 * may also be correctly instantiated using custom injection definitions.
 * 
 * The `Provider::share` method can be used to "recycle" an instance across 
 * many/all instantiations to allow "Singleton" type access to a resource 
 * without sacrificing the benefits of dependency injection or using "evil"
 * static/global references.
 * 
 * The Provider recursively instantiates dependency objects automatically.
 * For example, if class A has a dependency on class B and class B depends
 * on class C, the Provider will first provision an instance of class B
 * with the necessary dependencies in order to provision class A with an
 * instance of B.
 * 
 * ### BASIC PROVISIONING
 * 
 * ##### No Dependencies
 * 
 * If a class constructor specifies no dependencies and you don't need to 
 * share an instance there's little point in using the Provider to generate
 * it. However, for the sake of completeness consider that you can do the 
 * following and get equivalent results:
 * 
 * ```php
 * $obj1 = new Namespace\MyClass;
 * $obj2 = $provider->make('Namespace\\MyClass');
 * var_dump($obj1 === $obj2); // true
 * ```
 * 
 * ##### Concrete Typehinted Dependencies
 * 
 * If a class requires only concrete dependencies you can use the Provider to
 * inject it without specifying any injection definitions. So, for example, in
 * the following scenario you can use the Provider to automatically provision
 * `MyClass` with the required `DepClass` instance:
 * 
 * ```php
 * class DepClass
 * {
 * }
 * 
 * class AnotherDep
 * {
 * }
 * 
 * class MyClass
 * {
 *     public $dep1;
 *     public $dep2;
 *     public function __construct(DepClass $dep1, AnotherDep $dep2)
 *     {
 *         $this->dep1 = $dep1;
 *         $this->dep2 = $dep2;
 *     }
 * }
 * 
 * $myObj = $provider->make('MyClass');
 * var_dump($myObj->dep1 instanceof DepClass); // true
 * var_dump($myObj->dep2 instanceof AnotherDep); // true
 * ```
 * 
 * This method will scale to any number of typehinted class dependencies
 * specified in `__construct` methods.
 * 
 * ###### Scalar Dependencies
 * 
 * The design decision was explicitly made to disallow the specification of
 * non-object dependency parameters. Such values are frequently a failure to
 * implement recognized OOP design principles (though not always).
 * 
 * ### ADVANCED PROVISIONING
 * 
 * The provider cannot instantiate a typehinted abstract class or interface
 * without a bit of help. This is where injection definitions come in.
 * 
 * ##### Non-Concrete Dependencies
 * 
 * The Provider allows you to define the class names it should use to provision
 * objects with non-concrete method signatures. Consider:
 * 
 *  ```php
 * interface DepInterface
 * {
 *     public function doSomething();
 * }
 * 
 * class DepClass implements DepInterface
 * {
 *     public function doSomething()
 *     {
 *     }
 * }
 * 
 * class MyClass
 * {
 *     protected $dep;
 *     public function __construct(DepInterface $dep)
 *     {
 *         $this->dep = $dep;
 *     }
 * }
 * 
 * $provider->define('MyClass', ['DepClass']);
 * $myObj = $provider->make('MyClass');
 * var_dump($myObj instanceof MyClass); // true
 * ```
 * 
 * Custom injection definitions can also be specified using an instance
 * of the requisite class, so the following would work in the same manner as
 * above:
 * 
 * ```php
 * $provider->define('MyClass', [new DepClass]);
 * $myObj = $provider->make('MyClass');
 * var_dump($myObj instanceof MyClass); // true
 * ```
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class Provider implements ProviderInterface
{
    /**
     * An array of custom class instantiation parameters
     * @var array
     */
    protected $definitions = [];
    
    /**
     * An array of dependencies shared across the lifetime of the container
     * @var array
     */
    protected $shared = [];
    
    /**
     * A cache of reflected classes and constructor parameters
     * @var array
     */
    protected $reflCache;
    
    /**
     * Defines custom instantiation parameters for the specified class
     * 
     * @param string $class      Class name
     * @param mixed  $definition An array specifying an ordered list of custom
     *                           class names or an instance of the necessary class
     * 
     * @return Provider Returns object instance for method chaining
     */
    public function define($class, array $definition)
    {
        $lowClass = strtolower($class);
        $this->definitions[$lowClass] = $definition;
        return $this;
    }
    
    /**
     * Defines multiple custom instantiation parameters at once
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass
     *                        or ArrayAccess instance
     * 
     * @return int Returns the number of definitions stored by the operation.
     */
    public function defineAll($iterable)
    {
        if (!($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable)
        ) {
            throw new InvalidArgumentException(
                'Argument 1 passed to addAll must be an array, StdClass or '
                .'implement Traversable '
            );
        }
        
        $added = 0;
        foreach ($iterable as $class => $definition) {
            $lowClass = strtolower($class);
            $this->definitions[$lowClass] = $definition;
            ++$added;
        }
        return $added;
    }
    
    /**
     * Determines if an injection definition exists for the given class name
     * 
     * @param string $class Class name
     * 
     * @return bool Returns TRUE if a definition is stored or FALSE otherwise
     */
    public function isDefined($class)
    {
        $lowClass = strtolower($class);
        return isset($this->definitions[$lowClass]);
    }
    
    /**
     * Determines if a given class name is marked as shared
     * 
     * @param string $class Class name
     * 
     * @return bool Returns TRUE if a shared instance is stored or FALSE otherwise
     */
    public function isShared($class)
    {
        $lowClass = strtolower($class);
        return isset($this->shared[$lowClass])
            || array_key_exists($lowClass, $this->shared);
    }
    
    /**
     * Auto-injects dependencies upon instantiation of the specified class
     * 
     * @param string $class  Class name
     * @param array  $custom An optional array specifying custom instantiation 
     *                       parameters for this construction.
     * 
     * @return mixed A dependency-injected object
     * @throws ProviderDefinitionException On provisioning failure
     * @uses Provider::getInjectedInstance
     */
    public function make($class, array $custom = NULL)
    {
        $lowClass = strtolower($class);
        $shared   = $this->isShared($lowClass);
        
        if (isset($this->shared[$lowClass])) {
            return $this->shared[$lowClass];
        }
        
        if (NULL !== $custom) {
            $definition = $custom;
        } elseif (isset($this->definitions[$lowClass])) {
            $definition = $this->definitions[$lowClass];
        } else {
            $definition = NULL;
        }
        
        $obj = $this->getInjectedInstance($class, $definition);
        
        if ($shared) {
            $this->shared[$lowClass] = $obj;
        }
        return $obj;
    }
    
    /**
     * Forces re-instantiation of a shared class the next time it is requested
     * 
     * Note that this does not un-share the class; it simply removes the
     * instance from the shared cache so that it will be recreated the next
     * time a provision request is made. If the specified class isn't shared
     * to begin with, no action will be taken.
     * 
     * @param string $class Class name
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function refresh($class)
    {
        $lowClass = strtolower($class);
        if (isset($this->shared[$lowClass])) {
            $this->shared[$lowClass] = NULL;
        }
        return $this;
    }
    
    /**
     * Clear the injection definition for the specified class
     * 
     * Note that this operation will also remove any sharing definitions or
     * instances of the specified class.
     * 
     * @param string $class Class name
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function remove($class)
    {
        $class = strtolower($class);
        unset($this->definitions[$class]);
        unset($this->shared[$class]);
        
        return $this;
    }
    
    /**
     * Clear all injection definitions from the container
     * 
     * Note that this method also removes any shared definitions or instances.
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function removeAll()
    {
        $this->definitions = [];
        $this->shared = [];
        return $this;
    }
    
    /**
     * Stores a shared instance for the specified class
     * 
     * If no object instance is specified, the Provider will mark the class
     * name as "shared" and the next time the Provider is used to instantiate
     * the class it's instance will be stored and shared.
     * 
     * If an instance of the class is specified, it will be stored and shared
     * calls to `Provider::make` for the specified class until the shared 
     * instance is manually removed or refreshed.
     * 
     * @param string $class Class name
     * @param mixed  $obj   An instance of the specified class
     * 
     * @return Provider Returns object instance for method chaining
     * @throws InvalidArgumentException If passed object is not an instance 
     *                                  of the specified class
     */
    public function share($class, $obj = NULL)
    {
        $lowClass = strtolower($class);
        
        if (NULL === $obj) {
            $this->shared[$lowClass] = NULL;
        } elseif (!$obj instanceof $class) {
            $type = is_object($obj) ? get_class($obj) : gettype($obj);
            throw new InvalidArgumentException(
                get_class($this).'::share() argument 2 must be an '
                ."instance of $class: $type provided"
            );
        } else {
            $this->shared[$lowClass] = $obj;
        }
        
        return $this;
    }
    
    /**
     * Generate dependencies for a class without an injection definition
     * 
     * @param string $class       A class name
     * @param array  $ctorParams  An array of ReflectionParameter objects
     * 
     * @return array Returns an array of dependency instances to inject
     * @throws ProviderDefinitionException If a provision attempt is made for
     *                                     a class whose constructor specifies
     *                                     neither a typehint or NULL default 
     *                                     value for a parameter.
     * @used-by Provider::getInjectedInstance
     */
    protected function getDepsSansDefinition($class, array $ctorParams)
    {
        $deps = [];
        
        for ($i=0; $i<count($ctorParams); $i++) {
            
            if ($reflCls = $ctorParams[$i]->getClass()) {
                
                $deps[] = $this->make($reflCls->name);
                
            } elseif ($ctorParams[$i]->isDefaultValueAvailable()
                && NULL === $ctorParams[$i]->getDefaultValue()
            ) {
                $deps[] = NULL;
            } else {
                throw new ProviderDefinitionException(
                    "Cannot provision $class::__construct: scalar default ".
                    'values not allowed (argument ' . ($i+1) . ')'
                );
            }
        }
        return $deps;
    }
    
    /**
     * Generate dependencies for a class using an injection definition
     * 
     * @param string $class       A class name
     * @param array  $ctorParams  An array of ReflectionParameter objects
     * @param array  $def         An array specifying dependencies required
     *                            for object instantiation
     * 
     * @return array Returns an array of dependency instances to inject
     * @throws ProviderDefinitionException If a provisioning attempt is made
     *                                     using an invalid injection definition
     * @used-by Provider::getInjectedInstance
     */
    protected function getDepsWithDefinition($class, $ctorParams, $def)
    {
        $deps = [];
        $paramCount = count($ctorParams);
        
        for ($i=0; $i<$paramCount; $i++) {
            
            if (isset($def[$ctorParams[$i]->name])) {
                $deps[] = is_string($def[$ctorParams[$i]->name])
                    ? $this->make($def[$ctorParams[$i]->name])
                    : $def[$ctorParams[$i]->name];
                    
            } elseif ($reflCls = $ctorParams[$i]->getClass()) {
                
                if (!isset($this->reflCache[$reflCls->name])) {
                    if ($ctor = $reflCls->getConstructor()) {
                        $params = $ctor->getParameters();
                    } else {
                        $params = NULL;
                    }
                    $this->reflCache[$reflCls->name] = [
                        'class'=> $reflCls,'ctor'=> $params
                    ];
                }
                $deps[] = $this->make($reflCls->name);
                
            } elseif ($ctorParams[$i]->isDefaultValueAvailable()
                && NULL === $ctorParams[$i]->getDefaultValue()
            ) {
                $deps[] = NULL;
            } else {
                throw new ProviderDefinitionException(
                    "Cannot provision $class::__construct: argument " . ($i+1) .
                    ' must specify a class typehint or NULL default value'
                );
            }
        }
        
        return $deps;
    }
    
    /**
     * Return an instantiated object subject to user-specified definitions
     * 
     * @param string $class Class name
     * @param array  $def   An array specifying dependencies required for
     *                      object instantiation
     * 
     * @return mixed Returns A dependency-injected object
     * @throws ProviderDefinitionException If the class being provisioned doesn't
     *                                     exist and can't be autoloaded
     * @uses Provider::getDepsWithDefinition
     * @uses Provider::getDepsSansDefinition
     */
    protected function getInjectedInstance($class, $def)
    {
        if (isset($this->reflCache[$class])) {
            $refl   = $this->reflCache[$class]['class'];
            $params = $this->reflCache[$class]['ctor'];
        } else {
            try {
                $refl = new ReflectionClass($class);
            } catch (ReflectionException $e) {
                throw new ProviderDefinitionException(
                    "Provider instantiation failure: $class doesn't exist".
                    ' and could not be found by any registered autoloaders.'
                );
            }
            
            if ($ctor = $refl->getConstructor()) {
                $params = $ctor->getParameters();
            } else {
                $params = NULL;
            }
            
            $this->reflCache[$class] = ['class' => $refl, 'ctor' => $params];
        }
        
        if (!$params) {
            return new $class;
        } else {
            $deps = (NULL === $def)
                ? $this->getDepsSansDefinition($class, $params)
                : $this->getDepsWithDefinition($class, $params, $def);
            
            return $refl->newInstanceArgs($deps);
        }
    }
}
