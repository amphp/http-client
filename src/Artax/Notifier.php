<?php

/**
 * Artax Notifier Class File
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
    ArrayAccess,
    Traversable,
    StdClass;

/**
 * A central transit hub for all application events.
 * 
 * The Notifier exposes a simple interface for attaching a chain of listeners
 * to a managed event queue. You can attach any valid PHP callable to an event
 * queue with the following two exceptions:
 * 
 * 1. Global function name strings cannot be passed as listeners.
 * 2. Static class method strings (`MyClass::myMethod`) may not be used as 
 * listeners. This prohibition also applies to specifying a static PHP callback using
 * the array syntax as follows: `['MyClass', 'myStaticMethod']`.
 * 
 * There are a couple of reasons for these prohibitions. First, using global
 * functions and static methods flies in the face of generally accepted OOP
 * best-practices. Second, allowing these options would interfere with the 
 * Notifier's ability to ascertain whether a specified listener is a string 
 * class name that should be automatically instantiated and invoked when 
 * notified of an event. Which brings us to the main feature of the Notifier,
 * namely that:
 * 
 * > String class names can be specified as event listeners.
 * 
 * This allows us to lazy-load objects to act as event listeners. An instance
 * of the specified class will only be created if the relevant event occurs.
 * These classes are instantiated using the Artax Provider class and are
 * subject to the instantiation rules thereof, namely that:
 * 
 * * Class constructors must typehint arguments or specify a NULL default
 * value to be instantiated by the Provider.
 * 
 * So let's look at an example using the Notifier with a lazy class listener:
 * 
 * ```php
 * class ExampleDependency
 * {
 * }
 * 
 * class MyListener
 * {
 *     private $dep;
 *     
 *     public function __construct(ExampleDependency $dep)
 *     {
 *         $this->dep = $dep;
 *     }
 *     
 *     public function listenerAction($data)
 *     {
 *         echo "MyListener::listenerAction -- $data" . PHP_EOL;
 *     }
 * 
 *     public function __invoke($data)
 *     {
 *         $this->listenerAction($data);
 *     }
 * }
 * 
 * $notifier = new Notifier;
 * $notifier->push('my_event', 'MyListener');
 * $notifier->notify('my_event', 'my_event data');
 * ```
 * 
 * In the above code the Notifier will automatically instantiate MyListener
 * when notified of "my_event" and call its magic __invoke method using
 * any data from the notification call as parameter(s). In this way we're
 * able to reap the benefits of dependency injection in our class listeners
 * without being forced to instantiate listeners that we may or may not need.
 * 
 * Let's look at an example using the Notifier with Closure listeners:
 * 
 * ```php
 * $notifier = new Notifier;
 * $notifier->push('my_event_name', function() {
 *     echo 'My first mediated event!' . PHP_EOL;
 * });
 * $notifier->push('my_event_name', function() {
 *     echo 'Event #2' . PHP_EOL;
 * });
 * $notifier->notify('my_event_name');
 * ```
 * 
 * The above code will output the following:
 * 
 * ```
 * My first mediated event!
 * Event #2
 * ```
 * 
 * This simple example demonstrates the First-In-First-Out (FIFO) behavior 
 * of the `Notifier::notify` method. As you can see, event listeners are 
 * invoked according to their position in the queue. Since we pushed the 
 * "My first mediated event!" listener first, it will be the first listener 
 * executed when the object is notified that `my_event_name` has occurred.
 * 
 * Of course, we can also cheat and allow listeners to jump to the front of 
 * the queue using `Notifier::unshift`:
 * 
 * ```php
 * $notifier->unshift('my_event_name', function() {
 *     echo 'Sneaky listener #3 jumped to the front!' . PHP_EOL;
 * });
 * ```
 * 
 * At any time during the chain of listener execution for a given event, the
 * current listener may prevent listeners further down the queue from being
 * invoked by returning `FALSE`. When a listener returns `FALSE`,
 * `Notifier::notify` will return without executing any other listeners in
 * the chain.
 * 
 * For advanced notifier usage including lazy-loading class event listeners,
 * check out the relevant wiki entry:
 * 
 * https://github.com/rdlowrey/Artax/wiki/Event-Management
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 */
class Notifier implements Mediator
{
    
    /**
     * Tracks the number of listener invocations by event
     * @var array
     */
    private $invocationCount = array();
    
    /**
     * An dictionary mapping events to the relevant listeners
     * @var array
     */
    private $listeners = array();
    
    /**
     * Tracks the number of times each event is broadcast
     * @var array
     */
    private $notificationCount = array();
    
    /**
     * Dependency provider for listener lazy-loading
     * @var Provider
     */
    private $provider;
    
    /**
     * Injects dependency provider for lazy-loading object listeners
     * 
     * Please note that using the Provider in this way *DOES NOT* equate to
     * a Service Locator. The Provider *is* an actual dependency of the
     * Notifier. It is not obscuring or hiding real object dependencies. The
     * Provider is instead *required* by the Notifier to instantiate 
     * lazy-listeners specified by their string class names.
     * 
     * In almost every case, injecting a dependency injection container (DIC)
     * into an object results in a Service Locator anti-pattern. This is not
     * the case for the Notifier.
     * 
     * @param Provider $provider A dependency provider instance for lazy-loading
     *                           class-based listeners specified using a string
     *                           class name reference
     * 
     * @return void
     */
    public function __construct(Provider $provider)
    {
        $this->provider = $provider;
    }
    
    /**
     * Retrieve a list of all listeners queued for the specified event
     * 
     * @param string $eventName The event for which listeners should be returned
     * 
     * @return array Returns an array of queued listeners for the specified event
     *               or NULL if no listeners are registered for the event.
     */
    public function all($eventName)
    {
        return $this->count($eventName) ? $this->listeners[$eventName] : NULL;
    }
    
    /**
     * Clear all listeners from the specified event queue
     * 
     * Clears all listeners for the specified event. If an empty parameter value
     * is passed for the `$eventName`, all listeners will be cleared from all
     * events.
     * 
     * @param string $eventName Event identifier name
     * 
     * @return void
     */
    public function clear($eventName = NULL)
    {
        if (NULL !== $eventName && isset($this->listeners[$eventName])) {
            unset($this->listeners[$eventName]);
        } else {
            $this->listeners = array();
        }
    }
    
    /**
     * Retrieve a count of all listeners in the queue for a specific event
     * 
     * @param string $eventName Event identifier name
     * 
     * @return int Returns a count of queued listeners for the specified event
     */
    public function count($eventName)
    {
        return isset($this->listeners[$eventName])
            ? count($this->listeners[$eventName])
            : 0;
    }
    
    /**
     * Retrieve the first event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     * 
     * @return callable Returns the first event listener in the queue or `NULL`
     *                  if none exist for the specified event.
     */
    public function first($eventName)
    {
        return $this->count($eventName) ? $this->listeners[$eventName][0] : NULL;
    }
    
    /**
     * Get the total number of listeners that have been invoked for an event
     * 
     * @param string $event The name of the event for which an invocation count
     *                      is requested. If NULL is passed, the total count
     *                      of listener invocations for all events will be
     *                      returned. If a non-existent event is requested,
     *                      zero is returned.
     * 
     * @param int Returns the count of all invocations for the given event.
     */
    public function getInvocationCount($event = NULL)
    {
        if (NULL === $event) {
            return array_sum($this->invocationCount);
        } elseif (isset($this->invocationCount[$event])) {
            return $this->invocationCount[$event];
        } else {
            return 0;
        }
    }
    
    /**
     * Get the total number of times an event has been broadcast/notified
     * 
     * @param string $event The name of the event for which an notification 
     *                      count is requested. If NULL is passed, the total 
     *                      count of listener notifications for all events 
     *                      will be returned. If a non-existent event is 
     *                      requested, zero is returned.
     * 
     * @param int Returns the count of all notifications for the given event.
     */
    public function getNotificationCount($event = NULL)
    {
        if (NULL === $event) {
            return array_sum($this->notificationCount);
        } elseif (isset($this->notificationCount[$event])) {
            return $this->notificationCount[$event];
        } else {
            return 0;
        }
    }
    
    /**
     * Retrieve a list of all listened-for events
     * 
     * @return array Returns an array of listened-for events
     */
    public function keys()
    {
        return array_keys($this->listeners);
    }
    
    /**
     * Retrieve the last event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     * 
     * @return callable Returns the last event listener in the queue or `NULL`
     *                  if none exist for the specified event.
     */
    public function last($eventName)
    {
        return ($count = $this->count($eventName))
            ? $this->listeners[$eventName][$c-1]
            : NULL;
    }
    
    /**
     * Notify listeners that an event has occurred
     * 
     * Listeners act as a queue and are invoked in order until a listener in the
     * chain returns `FALSE` or the end of the queue is reached.
     * 
     * To differentiate between string class names with injection definitions
     * and valid PHP callback arrays we check the second array element. All
     * lazy listeners are stored inside an array alongside an injection definition
     * array (possibly empty). Meanwhile, valid PHP array callbacks will never 
     * store an array as their second data parameter.
     * 
     * @param string $event The event that occurred
     * 
     * @return int Returns a count of listeners invoked for the notified event
     */
    public function notify($event)
    {
        if (!isset($this->notificationCount[$event])) {
            $this->notificationCount[$event] = 0;
        }
        
        ++$this->notificationCount[$event];
        
        if ($count = $this->count($event)) {
            
            if (1 == func_num_args()) {
                $args = NULL;
            } else {
                $args = func_get_args();
                array_shift($args);
            }
            
            for ($i=0; $i<$count; $i++) {
            
                if (is_string($this->listeners[$event][$i])) {
                
                    $func = $this->provider->make($this->listeners[$event][$i]);
                    
                } elseif ((is_array($this->listeners[$event][$i])
                    && isset($this->listeners[$event][$i][1])
                    && is_array($this->listeners[$event][$i][1]))
                ) {
                    $func = $this->provider->make($this->listeners[$event][$i][0],
                        $this->listeners[$event][$i][1]
                    );
                } else {
                    
                    $func = $this->listeners[$event][$i];
                    
                }
                
                $result = $args ? call_user_func_array($func, $args) : $func();
                if ($result === FALSE) {
                    $count = $i+1;
                    break;
                }
            }
        }
        
        if (!isset($this->invocationCount[$event])) {
            $this->invocationCount[$event] = 0;
        }
        $this->invocationCount[$event] += $count;
        
        return $count;
    }
    
    /**
     * Remove the last listener from the end of the specified event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * 
     * @return mixed Returns popped listener on success or `NULL` if no listeners
     *               were found for the specified event.
     */
    public function pop($eventName)
    {
        if (isset($this->listeners[$eventName])) {
            return array_pop($this->listeners[$eventName]);
        }
        return NULL;
    }
    
    /**
     * Connect a listener to the end of the specified event queue
     * 
     * To enable listener lazy-loading, all string listeners are assumed to be 
     * class names and will be instantiated using the dependency provider. This
     * has one major ramification:
     * 
     * > **IMPORTANT:** global function names and static class methods cannot
     * be attached as event listeners. If you'd like more information, please
     * refer to the class-level Notifier documentation.
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  The event listener. Must be a valid callable or
     *                          a string class name
     * @param mixed  $lazyDef   An optional array or ArrayAccess specifying a
     *                          custom injection definition for lazy-loading
     *                          a string class name listener
     * 
     * @return int Returns the new number of listeners in the queue for the
     *             specified event.
     * 
     * @throws LogicException On non-callable, non-string $listener parameter
     * @uses Notifier::attach
     */
    public function push($eventName, $listener, $lazyDef = NULL)
    {
        $isString = is_string($listener);
        
        if (!($isString || is_callable($listener))) {
            throw new InvalidArgumentException(
                'Argument 2 for ' . get_class($this) .'::push must be a valid '
                .'callable or string class name'
            );
        }
        if (NULL !== $lazyDef
            && !($lazyDef instanceof ArrayAccess || is_array($lazyDef))
        ) {
            throw new InvalidArgumentException(
                'Argument 3 for ' . get_class($this) .'::push must be an array '
                .'or object that implements ArrayAccess'
            );
        }
        
        $listener = $isString && NULL !== $lazyDef
            ? array($listener, $lazyDef)
            : $listener;
        
        if ( ! isset($this->listeners[$eventName])) {
            $this->listeners[$eventName]   = array();
            $this->listeners[$eventName][] = $listener;
            return 1;
        }
        return array_push($this->listeners[$eventName], $listener);
    }
    
    /**
     * Iterates through the items in the order they are traversed, adding them
     * to the event queue found in the key.
     * 
     * Lazy-loading via string class names makes determining what kind of 
     * listener was specified a bit complicated, so we utilize some simplifying
     * assumptions:
     * 
     * * Any string specified as a listener is always assumed to be a string
     * class name for lazy-loading. This assumption prevents us from accepting
     * string names of global functions and static class methods as listeners.
     * This also prevents static methods from being specified using the array
     * callback style `['MyClass', 'myStaticMethod']` as this construction can't
     * be differentiated from two separate lazy listeners.
     * 
     * * In order to lazy-load class listeners whose constructors don't specify
     * concrete class names we need a way to pass along dependency definitions
     * along with the class name strings. For this we use a simple array. The
     * issue then becomes differentiating in a list of multiple listeners passed
     * to `Notifier::pushAll` whether an array is a PHP callback or a string
     * class name with a matching injection definition array. This is achieved
     * with a string of checks in an `elseif` control structure.
     * 
     * @param mixed The variable to loop through: array|Traversable|StdClass
     * 
     * @return int Returns the total number of listeners added across all event
     *             queues as a result of the method call.
     *
     * @throws InvalidArgumentException when $iterable is not an array, Traversable,
     *         or StdClass instance.
     */
    public function pushAll($iterable) {
        if (!($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable))
        {
            throw new InvalidArgumentException(
                'Argument 1 passed to '.get_class($this).'::pushAll must be an '
                .'array, StdClass or object implementing Traversable'
            );
        }
        $added = 0;
        foreach ($iterable as $event => $val) {
            $isArr = is_array($val);
            if (!$isArr || is_callable($val)) {
                $this->push($event, $val);
                ++$added;
                continue;
            } elseif (isset($val[0])
                && isset($val[1])
                && count($val) === 2
                && is_string($val[0])
                && is_array($val[1])
                && !is_callable($val[1])
            ) {
                // should be a class name with an injection definition
                $this->push($event, $val[0], $val[1]);
                ++$added;
            } else {
                // we have to assume it's a list of listeners at this point
                foreach ($val as $listener) {
                    $this->push($event, $listener);
                    ++$added;
                }
            }
        }
        return $added;
    }
    
    /**
     * Remove a listener from the front of the specified event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * 
     * @return mixed Returns shifted listener on success or `NULL` if no listeners
     *               were found for the specified event.
     */
    public function shift($eventName)
    {
        if (isset($this->listeners[$eventName])) {
            return array_shift($this->listeners[$eventName]);
        }
        return NULL;
    }
    
    /**
     * Connect an event listener to the front of the specified event queue
     * 
     * This function behaves in exactly the same manner as `Notifier::push` with
     * the exception being that `Notifier::unshift` attaches listeners to the
     * front of the specified event queue.
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  The event listener. Must be a valid callable or
     *                          a string class name
     * @param mixed  $lazyDef   An optional array or ArrayAccess specifying a
     *                          custom injection definition for lazy-loading
     *                          a string class name listener
     * 
     * @return int Returns the new number of listeners in the queue for the
     *             specified event.
     */
    public function unshift($eventName, $listener, $lazyDef = NULL)
    {
        $isString = is_string($listener);
        
        if (!($isString || is_callable($listener))) {
            throw new InvalidArgumentException(
                'Argument 2 for ' . get_class($this) .'::push must be a valid '
                .'callable or string clas name'
            );
        }
        if (NULL !== $lazyDef
            && !($lazyDef instanceof ArrayAccess || is_array($lazyDef))
        ) {
            throw new InvalidArgumentException(
                'Argument 3 for ' . get_class($this) .'::push must be an array '
                .'or object that implements ArrayAccess'
            );
        }
        
        $listener = $isString && NULL !== $lazyDef
            ? array($listener, $lazyDef)
            : $listener;
        
        if ( ! isset($this->listeners[$eventName])) {
            $this->listeners[$eventName]   = array();
            $this->listeners[$eventName][] = $listener;
            return 1;
        }
        return array_unshift($this->listeners[$eventName], $listener);
    }
}
