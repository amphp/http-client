<?php

/**
 * Artax Provider Class File
 * 
 * PHP version 5.3
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    All code subject to the ${license.name}
 * @version    ${project.version}
 */

namespace Artax;
use InvalidArgumentException,
    ReflectionClass,
    ReflectionException,
    ArrayAccess,
    Traversable,
    StdClass;
  
/**
 * A dependency injection/provider class
 * 
 * ### What the Provider does
 * 
 * The Provider is a dependency injection container existing specifically to
 * enable lazy instantiation of event listeners. `Provider::make` automagically
 * instantiates an instance of the specified class using reflection to
 * determine the class's constructor parameters. Non-concrete dependencies
 * (interfaces, abstract classes )can also be correctly instantiated by 
 * specifying custom injection definitions.
 * 
 * The `Provider::share` method can be used to "recycle" an instance across 
 * many/all instantiations to allow "Singleton" type access to a resource 
 * without sacrificing the benefits of dependency injection or using evil
 * `static` or global references.
 * 
 * The Provider also recursively instantiates dependencies when building a
 * new object. For example, if class A has a dependency on class B and class
 * B depends on class C, the Provider will first provision an instance of 
 * class B with the necessary dependencies in order to provision class A 
 * with an instance of B.
 * 
 * ### Basic provisioning
 * 
 * - No Dependencies
 * 
 * If a class constructor specifies no dependencies and you don't need to 
 * share an instance there's little point in using the Provider to generate
 * it. However, for the sake of completeness consider that you can do the 
 * following and get equivalent results:
 * 
 * 
 *     class MyClass
 *     {
 *         public function __construct()
 *         {
 *             $this->val = 42;
 *         } 
 *     }
 *     
 *     $obj1     = new MyClass;
 *     $obj2     = $provider->make('MyClass');
 *     
 *     var_dump($obj2->val == 42); // true
 *     var_dump($obj1 === $obj2); // true
 * 
 * 
 * - Concrete Typehinted Dependencies
 * 
 * If a class requires only concrete dependencies you can use the Provider to
 * inject it without specifying any injection definitions. So, for example, in
 * the following scenario you can use the Provider to automatically provision
 * `MyClass` with the required `DepClass` instance:
 * 
 * 
 *     class DepClass {}
 *     
 *     class AnotherDep
 *     {
 *         public function __construct(DepClass $dep){}
 *     }
 *     
 *     class MyClass
 *     {
 *         public function __construct(DepClass $dep1, AnotherDep $dep2) {
 *             $this->dep1 = $dep1;
 *             $this->dep2 = $dep2;
 *         }
 *     }
 *     
 *     $myObj = $provider->make('MyClass');
 *     
 *     var_dump($myObj->dep1 instanceof DepClass); // true
 *     var_dump($myObj->dep2 instanceof AnotherDep); // true
 * 
 * 
 * This method will scale to any number of typehinted class dependencies
 * specified in `__construct` methods.
 * 
 * - Scalar Dependencies
 * 
 * The design decision was explicitly made to disallow the specification of
 * non-object dependency parameters and encourage more object-oriented 
 * code design.
 * 
 * ### ADVANCED PROVISIONING
 * 
 * The provider cannot instantiate a typehinted abstract class or interface
 * without a bit of help. This is where injection definitions come in.
 * 
 * - Non-Concrete Dependencies
 * 
 * The Provider allows you to define the class names it should use to provision
 * objects with non-concrete method signatures. To specify a custom
 * injection definition you must pass `Provider::make` an array mapping the
 * relevant constructor parameter(s) variable name(s) to the custom class(es) 
 * that should be instantiated instead. Consider:
 * 
 * 
 *     interface DepInterface
 *     {
 *         public function walkOnWater();
 *     }
 *     
 *     interface AnotherInterface
 *     {
 *         public function slayDragons();
 *     }
 *     
 *     class DepClass implements DepInterface
 *     {
 *         public function walkOnWater() {}
 *     }
 *     
 *     class AnotherDep implements AnotherInterface
 *     {
 *         public function slayDragons() {}
 *     }
 *     
 *     class MyClass
 *     {
 *         public function __construct(DepInterface $dep, AnotherInterface $ai) {
 *             $this->dep = $dep;
 *             $this->ai  = $ai;
 *         }
 *     }
 *     
 *     $obj = $provider->make('MyClass', array('dep'=>'DepClass', 'ai'=>'AnotherDep'));
 *     
 *     var_dump($obj instanceof MyClass); // true
 * 
 * 
 * Custom injection definitions can also be specified using an instance
 * of the requisite class, so the following would work in the same manner as
 * above:
 * 
 *     $provider->define('MyClass', array('dep' => new DepClass, 'ai' => new AnotherDep));
 *     $myObj = $provider->make('MyClass');
 *     var_dump($myObj instanceof MyClass); // true
 * 
 * Note that when specifying custom injection definitions you don't have to
 * specifying a custom value for every constructor parameter. You only need
 * to specify the parameters you need/want to alter.
 * 
 * - Specifying injection definitions ahead of time
 * 
 * You can avoid passing custom definitions to `Provider::make` by using
 * `Provider::define` ahead of time. Consider:
 * 
 *     interface AzorAhai
 *     {
 *         public function bringLight() {}
 *     }
 *     
 *     class JohnSnow implements AzorAhai
 *     {
 *         public function bringLight() {}
 *     }
 *     
 *     class WindsOfWinter
 *     {
 *         public function __construct(AzorAhai $azor) {
 *             $this->azor = $azor;
 *         }
 *     }
 *     
 *     $provider->define('WindsOfWinter', array('azor' => 'JohnSnow'));
 *     $wow = $provider->make('WindsOfWinter');
 *     
 *     var_dump($wow instanceof WindsOfWinter); // true
 * 
 * - Sharing dependencies across the Provider scope
 * 
 * When you have objects that should logically be limited to a single 
 * instance (think loggers or database connections) developers have
 * traditionally fallen back to unsatisfactory solutions like `static`,
 * Singletons and `global`. A Provider instance allows you to forego these 
 * evils and use dependency injection to maintain code testability by sharing
 * objects. Consider:
 * 
 * 
 *     class Logger
 *     {
 *         public $author = 'Bilbo';
 *     }
 *     
 *     class Journey
 *     {
 *         public function __construct(Logger $logger) {
 *             $this->logger = $logger;
 *         }
 *     }
 *     
 *     class LordOfTheRings
 *     {
 *         public function __construct(Journey $journey) {
 *             $this->journey = $journey;
 *         }
 *     }
 *     
 *     $oneLoggerToRuleThemAll = new Logger;
 *     $oneLoggerToRuleThemAll->author = 'Frodo';
 *     $provider->share('Logger', $oneLoggerToRuleThemAll);
 *     
 *     $fellowship = $provider->make('LordOfTheRings');
 *     var_dump($fellowship->journey->logger->author); // Frodo
 * 
 * 
 * Once an instance is shared it remains shared for all calls to 
 * `Provider::make` until it is explicitly unshared with `Provider::remove`
 * or refreshed using `Provider::refresh`.
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
    protected $definitions = array();
    
    /**
     * An array of dependencies shared across the lifetime of the container
     * @var array
     */
    protected $shared = array();
    
    /**
     * A cache of reflected classes and constructor parameters
     * @var array
     */
    protected $reflCache = array();
    
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
        $this->definitions = array();
        $this->shared = array();
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
        $deps = array();
        
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
        $deps       = array();
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
                    $this->reflCache[$reflCls->name] = array(
                        'class' => $reflCls, 'ctor' => $params
                    );
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
                    ' and could not be found by any registered autoloaders. '.
                    'If you continue to receive this message and you\'re '.
                    'sure the class exists or is autoloadable, try switching '.
                    'to AX_DEBUG level 2 for extended debug output',
                    NULL, $e
                );
            }
            
            if ($ctor = $refl->getConstructor()) {
                $params = $ctor->getParameters();
            } else {
                $params = NULL;
            }
            
            $this->reflCache[$class] = array('class' => $refl, 'ctor' => $params);
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
