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
 * The provider expects dependency definitions and operations dealing with these
 * definitions to use the dot-notation format to represent fully qualified PHP
 * class names. Some examples of dot-notation class names and how the Provider
 * will parse them:
 * 
 * ```php
 * Namespace.ClassName         --> \Namespace\ClassName
 * MyClass                     --> \MyClass
 * MyNamespace.Package.MyClass --> \MyNamespace\Package\MyClass
 * ```
 * 
 * As you can see, this format simply replaces standard backslash namespace 
 * separators with a dot (.) character. All dot-notation class names are 
 * resolved relative to the root namespace, so a leading dot is unnecessary.
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
 * $obj2 = $provider->make('Namespace.MyClass');
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
 * The Provider allows you to define the dot-notation class names it should use to
 * provision objects with non-concrete method signatures. Consider:
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
     * A DotNotation object for parsing dot-notation class names
     * @var DotNotation
     */
    protected $dotNotation;
    
    /**
     * An array of custom class instantiation parameters
     * @var array
     */
    protected $definitions;
    
    /**
     * An array of dependencies shared across the lifetime of the container
     * @var array
     */
    protected $shared;
    
    /**
     * Initializes DotNotation object dependency
     * 
     * @param DotNotation $dotNotation A DotNotation object for class name parsing
     * 
     * @return void
     */
    public function __construct(DotNotation $dotNotation)
    {
        $this->definitions  = [];
        $this->shared       = [];
        $this->dotNotation  = $dotNotation;
    }
    
    /**
     * Defines custom instantiation parameters for the specified class
     * 
     * @param string $dotStr     The relevant dot-notation class name
     * @param mixed  $definition An array or ArrayAccess instance specifying an
     *                           ordered list of custom dot-notation class names
     *                           or an instance of the necessary 
     * 
     * @return Provider Returns object instance for method chaining
     */
    public function define($dotStr, $definition)
    {
        if (!($definition instanceof \ArrayAccess || is_array($definition))) {
            throw new \InvalidArgumentException(
                'Argument 2 passed to ' . get_class($this) . '::add must be an '
                .'array or implement ArrayAccess'
            );
        }
        
        if (isset($this->shared[$dotStr]) && empty($definition['_shared'])) {
            unset($this->shared[$dotStr]);
        }
        
        $this->definitions[$dotStr] = $definition;
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
        if (!(is_array($iterable)
            || $iterable instanceof \StdClass
            || $iterable instanceof \Traversable)
        ) {
            throw new \InvalidArgumentException(
                'Argument 1 passed to addAll must be an array, StdClass or '
                .'implement Traversable '
            );
        }
        
        $added = 0;
        foreach ($iterable as $dotStr => $definition) {
            $this->define($dotStr, $definition);
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
     * the original invocation where you specified the dot-string class name
     * as shared.
     * 
     * It is important to note that once an object is shared it will NOT accept
     * custom instantiation definitions. If you need customized instantiation
     * parameters for a shared instance you'll need to first call `Provider::remove`
     * or `Provider::refresh` to allow a new instantiation of the shared object.
     * 
     * @param string $dotStr A dot notation class name
     * @param mixed  $custom An optional array or ArrayAccess instance specifying
     *                       custom instantiation parameters for this construction
     * 
     * @return mixed A dependency-injected object
     * @throws InvalidArgumentException On invalid custom injection definition
     * @uses Provider::getInjectedInstance
     */
    public function make($dotStr, $custom = NULL)
    {
        if (isset($this->shared[$dotStr])) {
            return $this->shared[$dotStr];
        }
        
        if (NULL !== $custom) {
            if (!(is_array($custom) || $custom instanceof \ArrayAccess)) {
                throw new \InvalidArgumentException(
                    'Argument 2 passed to ' . get_class($this) . '::make must be'
                    .'an array or implement ArrayAccess'
                );
            }
            $definition = $custom;
            
        } elseif (isset($this->definitions[$dotStr])) {
            $definition = $this->definitions[$dotStr];
        } else {
            $definition = NULL;
        }
        
        $obj = $this->getInjectedInstance($dotStr, $definition);
        
        if (!empty($definition['_shared'])) {
            $this->shared[$dotStr] = $obj;
        }
        
        return $obj;
    }
    
    /**
     * Clear the injection definition for the specified class
     * 
     * Note that this operation will also remove any shared instances of the
     * specified class.
     * 
     * @param string $dotStr A dot-notation class name
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function remove($dotStr)
    {
        if (isset($this->definitions[$dotStr])) {
            unset($this->definitions[$dotStr]);
        }
        if (isset($this->shared[$dotStr])) {
            unset($this->shared[$dotStr]);
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
     * @param string $dotStr The dot-notation class name to refresh
     * 
     * @return Provider Returns object instance for method chaining.
     */
    public function refresh($dotStr)
    {
        if (isset($this->shared[$dotStr])) {
            unset($this->shared[$dotStr]);
        }
        return $this;
    }
    
    /**
     * Return an instantiated object subject to user-specified definitions
     * 
     * @param string $dotStr A dot-notation class name
     * @param mixed  $def    An array or ArrayAccess implementation specifying
     *                       dependencies required for object instantiation
     * 
     * @return mixed Returns A dependency-injected object
     * @throws InvalidArgumentException
     */
    protected function getInjectedInstance($dotStr, $def)
    {
        $class = $this->dotNotation->parse($dotStr);
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
     * @param string $class A dot-notation class name
     * @param array  $args  An array of ReflectionParameter objects
     * @param mixed  $def   An array or ArrayAccess implementation specifying
     *                      dependencies required for object instantiation
     * 
     * @return array Returns an array of dependency instances to inject
     * @throws InvalidArgumentException If a provision attempt is made for a
     *                                  using an invalid injection definition
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
     * @param string $class A dot-notation class name
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
                $dotStr = $this->dotNotation->parse($param->name, TRUE);
                $deps[] = isset($this->shared[$dotStr])
                    ? $this->shared[$dotStr]
                    : new $param->name;
            }
        }
        return $deps;
    }
    
    /**
     * Implements ArrayAccess interface to add injection definitions
     * 
     * @param string $offset Dot-notation string class name
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
     * @param string $offset Dot-notation string class name
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
     * @param string $offset Dot-notation string class name
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
     * @param string $offset Dot-notation string class name
     * 
     * @return mixed An array, StdClass or ArrayAccess instance
     */
    public function offsetGet($offset)
    {
        return $this->definitions[$offset];
    }
}
