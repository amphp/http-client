<?php

/**
 * Artax Provider Class File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Ioc;
  
/**
 * A dependency injection/provider class
 * 
 * The Provider is a dependency injection container existing specifically to
 * enable lazy instantiation of event listeners.
 * 
 * ### BASIC PROVISIONING
 * 
 * ##### No Dependencies
 * 
 * If a class constructor specifies no dependencies there's absolutely no point
 * in using the Provider to generate it. However, for the sake of completeness
 * consider that you can do the following and get equivalent results:
 * 
 * ```php
 * $obj1 = new Namespace\MyClass;
 * $obj2 = $provider->make('Namespace\MyClass');
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
 * non-object dependency parameters. Such values are usually a failure to correctly
 * implement recognized OOP design principles. Further, objects don't really
 * "depend" on scalar values as they don't expose any functionality. If this
 * behavior creates a problem in your application it may be worthwhile to
 * reconsider how you're attacking your current problem.
 * 
 * ### ADVANCED PROVISIONING
 * 
 * The provider cannot instantiate a typehinted abstract class or interface without
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
 * Custom injection definitions can also be specified using a specific instance
 * of the requisite class, so the following would work in the same manner as
 * above:
 * 
 * ```php
 * $provider->define('MyClass', [new DepClass]);
 * $myObj = $provider->make('MyClass');
 * var_dump($myObj instanceof MyClass); // true
 * ```
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
class Provider implements ProviderInterface, \ArrayAccess
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
        unset($this->shared[$class]);
        $this->definitions[$class] = $definition;
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
        if (!($iterable instanceof \StdClass
            || is_array($iterable)
            || $iterable instanceof \Traversable)
        ) {
            throw new \InvalidArgumentException(
                'Argument 1 passed to addAll must be an array, StdClass or '
                .'implement Traversable '
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
     * Factory method for auto-injecting dependencies upon instantiation
     * 
     * If an optional custom instantiation definition is supplied it will completely
     * replace (not append) any existing definitions for the specified class,
     * but only in the context of this single invocation. The exception to this
     * rule is using a custom definition to share the provisioned instance.
     * For example:
     * 
     * ```php
     * $myObj = $provider->make('Namespace.MyClass', ['_shared']);
     * ```
     * 
     * This will store the provisioned instance of `Namespace\MyClass` in the
     * Provider's shared cache and all future requests to the provider for an
     * injected instance of `Namespace\MyClass` will return the originally
     * created object (unless you manually clear it from the cache). So the next
     * time you call:
     * 
     * ```php
     * $myObj = $provider->make('Namespace.MyClass');
     * ```
     * 
     * you will actually be given a reference to the same class you created in
     * the original invocation where you specified the class as shared.
     * 
     * It is important to note that once an object is shared it will NOT accept
     * custom instantiation definitions. If you need customized instantiation
     * parameters for a shared instance you'll need to first call `Provider::remove`
     * or `Provider::refresh` to allow a new instantiation of the shared object.
     * 
     * @param string $class  Class name
     * @param array  $custom An optional array specifying custom instantiation 
     *                       parameters for this construction.
     * 
     * @return mixed A dependency-injected object
     * @throws InvalidArgumentException On invalid custom injection definition
     * @uses Provider::getInjectedInstance
     */
    public function make($class, array $custom = NULL)
    {
        if (isset($this->shared[$class])) {
            return $this->shared[$class];
        }
        
        if (NULL !== $custom) {
            $definition = $custom;            
        } elseif (isset($this->definitions[$class])) {
            $definition = $this->definitions[$class];
        } else {
            $definition = NULL;
        }
        
        $shared = FALSE;
        if ($definition
            && (($shared = array_search('_shared', $definition) !== FALSE))
        ){
            unset($definition[$shared]);
            $definition = array_values($definition);
            $shared = TRUE;
        }
        
        $obj = $this->getInjectedInstance($class, $definition);
        if ($shared) {
            $this->shared[$class] = $obj;
        }
        return $obj;
    }
    
    /**
     * Clear the injection definition for the specified class
     * 
     * Note that this operation will also remove any shared instances of the
     * specified class.
     * 
     * @param string $class Class name
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function remove($class)
    {
        if (isset($this->definitions[$class])) {
            unset($this->definitions[$class]);
        }
        if (isset($this->shared[$class])) {
            unset($this->shared[$class]);
        }
        return $this;
    }
    
    /**
     * Clear all injection definitions from the container
     * 
     * Note that this method also removes any shared instances as well.
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
     * Forces re-instantiation of a shared class the next time it is requested
     * 
     * Note that this does not un-share the class; it simply removes the
     * instance from the shared cache so that it will be recreated the next time
     * a provision request is made.
     * 
     * @param string $class Class name
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function refresh($class)
    {
        if (isset($this->shared[$class])) {
            unset($this->shared[$class]);
        }
        return $this;
    }
    
    /**
     * Determines if a shared instance of the given class name is currently stored
     * 
     * @param string $class Class name
     * 
     * @return bool Returns TRUE if a shared instance is stored or FALSE otherwise
     */
    public function isShared($class)
    {
        return isset($this->shared[$class]);
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
        return isset($this->definitions[$class]);
    }
    
    /**
     * Stores a shared instance for the specified class
     * 
     * @param string $class Class name
     * @param mixed  $obj   An instance of the specified class
     * 
     * @return Provider Returns object instance for method chaining
     * @throws InvalidArgumentException If passed object is not an instance of
     *                                  the specified class
     */
    public function share($class, $obj)
    {
        if (!$obj instanceof $class) {
            $type = is_object($obj) ? get_class($obj) : gettype($obj);
            throw new \InvalidArgumentException(
                'Object at '.get_class($this).'::share Argument 2 must be an '
                ."instance of $class: $type provided"
            );
        }
        $this->shared[$class] = $obj;
        return $this;
    }
    
    /**
     * Return an instantiated object subject to user-specified definitions
     * 
     * @param string $class Class name
     * @param array  $def   An array specifying dependencies required for
     *                      object instantiation
     * 
     * @return mixed Returns A dependency-injected object
     * @throws InvalidArgumentException
     */
    protected function getInjectedInstance($class, $def)
    {
        $refl  = new \ReflectionClass($class);
        $ctor  = $refl->getConstructor();
        
        if (!$ctor) {
            return new $class;
        }
        
        $args = $ctor->getParameters();
        
        if (NULL === $def) {
            $deps = $this->getDepsSansDefinition($class, $args);
        } else {
            $deps = $this->getDepsWithDefinition($class, $args, $def);
        }
        return $refl->newInstanceArgs($deps);
    }
    
    /**
     * Generate dependencies for a class using an injection definition
     * 
     * @param string $class Class name
     * @param array  $args  An array of ReflectionParameter objects
     * @param array  $def   An array specifying dependencies required for
     *                      object instantiation
     * 
     * @return array Returns an array of dependency instances to inject
     * @throws InvalidArgumentException If a provision attempt is made using
     *                                  an invalid injection definition
     * @used-by Provider::getInjectedInstance
     */
    protected function getDepsWithDefinition($class, $args, $def)
    {
        $deps = [];
        
        for ($i=0; $i<count($args); $i++) {
        
            if (!isset($def[$i])) {
                throw new \InvalidArgumentException(
                    'Invalid injection definition: no argument defined for '
                    .$args[$i]->getClass()->name ." at $class::__construct "
                    .'argument ' . $i+1
                );
            } elseif (is_string($def[$i])) {
                if (isset($this->shared[$def[$i]])) {
                    $deps[] = $this->shared[$def[$i]];
                } else {
                    $deps[] = $this->make($def[$i]);
                }
            } else {
                $expected = $args[$i]->getClass()->name;
                if ($def[$i] instanceof $expected) {
                    $deps[] = $def[$i];
                    continue;
                }
                $type = is_object($def[$i])
                    ? get_class($def[$i])
                    : gettype($def[$i]);
                
                throw new \InvalidArgumentException(
                    "Invalid injection definition: $class::__construct expects"
                    ."$expected instance at argument " . $i+1
                    ." -- $type provided"
                );
            }
        }
        
        return $deps;
    }
    
    /**
     * Generate dependencies for a class without an injection definition
     * 
     * @param string $class Class name
     * @param array  $args  An array of ReflectionParameter objects
     * 
     * @return array Returns an array of dependency instances to inject
     * @throws InvalidArgumentException If a provision attempt is made for a
     *                                  class that is missing typehints
     * @used-by Provider::getInjectedInstance
     */
    protected function getDepsSansDefinition($class, array $args)
    {
        $deps = [];
        
        for ($i=0; $i<count($args); $i++) {
            if (!$param = $args[$i]->getClass()) {
                throw new \InvalidArgumentException(
                    "Cannot provision $class::__construct: missing typehint "
                    .'for argument' . $i+1
                );
            } else {
                $deps[] = isset($this->shared[$class])
                    ? $this->shared[$class]
                    : new $param->name;
            }
        }
        return $deps;
    }
    
    /**
     * Implements ArrayAccess interface to add injection definitions
     * 
     * @param string $offset Class name
     * @param mixed $value  An array, StdClass or ArrayAccess instance
     * 
     * @return void
     * @uses Provider::define
     */
    public function offsetSet($offset, $value)
    {
      $this->define($offset, $value);
    }
    
    /**
     * Implements ArrayAccess interface to determine if a definition exists
     * 
     * @param string $offset Class name
     * 
     * @return bool Returns TRUE if a definition exists or FALSE if not.
     */
    public function offsetExists($offset)
    {
      return isset($this->definitions[$offset]);
    }
    
    /**
     * Implements ArrayAccess interface to remove a specified definition
     * 
     * @param string $offset Class name
     * 
     * @return void
     * @uses Provider::remove
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
    
    /**
     * Implements ArrayAccess interface to retrieve a specified definition
     * 
     * @param string $offset Class name
     * 
     * @return mixed An array, StdClass or ArrayAccess instance
     */
    public function offsetGet($offset)
    {
        return $this->definitions[$offset];
    }
}
