<?php

/**
 * Artax Mediator Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Events;

/**
 * Mediator Class
 * 
 * The Mediator class acts as a central transit hub for all application events.
 * 
 * The Mediator exposes a simple interface for attaching a chain of listeners
 * to a managed event queue. You can attach any valid PHP callable to an event
 * queue with the following two exceptions:
 * 
 * 1. Global function name strings cannot be passed as listeners.
 * 2. Static class method strings (`MyClass::myMethod`) may not be used as 
 * listeners. This prohibition also applies to specifying a static PHP callback using
 * the array syntax as follows: `['MyClass', 'myStaticMethod']`.
 * 
 * A simple example:
 * 
 * ```php
 * $mediator = new Artax\Events\Mediator;
 * $mediator->push('my_event_name', function() {
 *     echo 'My first mediated event!' . PHP_EOL;
 * });
 * $mediator->push('my_event_name', function() {
 *     echo 'Event #2' . PHP_EOL;
 * });
 * $mediator->notify('my_event_name');
 * ```
 * 
 * The above code will output the following:
 * 
 * ```
 * My first mediated event!
 * Event #2
 * ```
 * 
 * This simple example demonstrates the First-In-First-Out (FIFO) behavior of the
 * `Mediator::notify` method. As you can see, event listeners are invoked 
 * according to their position in the queue. Since we pushed the "My first mediated
 * event!" listener first, it's the first listener executed when the object is 
 * notified of the `my_event_name` event.
 * 
 * Of course, we can cheat and allow listeners to jump to the front of the queue
 * using `Mediator::unshift`:
 * 
 * ```php
 * $mediator->unshift('my_event_name', function() {
 *     echo 'Sneaky listener #3 jumped to the front!' . PHP_EOL;
 * });
 * ```
 * 
 * For advanced mediator usage including lazy-loading class event listeners,
 * check out the relevant wiki entries on github:
 * 
 * https://github.com/rdlowrey/Artax/wiki/Event-Management
 * https://github.com/rdlowrey/Artax/wiki/Advanced-Events
 * 
 * @category   Artax
 * @package    Events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class Mediator implements MediatorInterface
{
    /**
     * An array of event listeners
     * @var array
     */
    protected $listeners = [];
    
    /**
     * Dependency provider for listener lazy-loading
     * @var Provider
     */
    protected $provider;
    
    /**
     * Injects dependency provider for lazy-loading object listeners
     * 
     * If no dependency provider is specified, a factory is used to create one.
     * 
     * @param Artax\Ioc\Provider $provider A dependency provider instance for
     *                                     lazy-loading object listeners
     * 
     * @return void
     */
    public function __construct(\Artax\Ioc\Provider $provider)
    {
        $this->provider = $provider;
    }
    
    /**
     * Connect a listener to the end of the specified event queue
     * 
     * To enable listener lazy-loading, all string listeners are assumed to be 
     * class names and will be instantiated using the dependency provider. This
     * has one major ramification:
     * 
     * > **IMPORTANT:** global function names and static class methods cannot
     * be attached as event listeners.
     * 
     * From the standpoint of object-orientation this is actually a positive in 
     * that it discourages problematic design strategies.
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
     * @uses Mediator::attach
     */
    public function push($eventName, $listener, $lazyDef = NULL)
    {
        $isString = is_string($listener);
        
        if (!($isString || is_callable($listener))) {
            throw new \InvalidArgumentException(
                'Argument 2 for ' . get_class($this) .'::push must be a valid '
                .'callable or string class name'
            );
        }
        if (NULL !== $lazyDef
            && !($lazyDef instanceof \ArrayAccess || is_array($lazyDef))
        ) {
            throw new \InvalidArgumentException(
                'Argument 3 for ' . get_class($this) .'::push must be an array '
                .'or object that implements ArrayAccess'
            );
        }
        
        $listener = $isString && NULL !== $lazyDef
            ? [$listener, $lazyDef]
            : $listener;
        
        if ( ! isset($this->listeners[$eventName])) {
            $this->listeners[$eventName]   = [];
            $this->listeners[$eventName][] = $listener;
            return 1;
        }
        return array_push($this->listeners[$eventName], $listener);
    }
    
    /**
     * Connect an event listener to the front of the specified event queue
     * 
     * This function behaves in exactly the same manner as `Mediator::push` with
     * the exception being that `Mediator::unshift` attaches listeners to the
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
            throw new \InvalidArgumentException(
                'Argument 2 for ' . get_class($this) .'::push must be a valid '
                .'callable or string clas name'
            );
        }
        if (NULL !== $lazyDef
            && !($lazyDef instanceof \ArrayAccess || is_array($lazyDef))
        ) {
            throw new \InvalidArgumentException(
                'Argument 3 for ' . get_class($this) .'::push must be an array '
                .'or object that implements ArrayAccess'
            );
        }
        
        $listener = $isString && NULL !== $lazyDef
            ? [$listener, $lazyDef]
            : $listener;
        
        if ( ! isset($this->listeners[$eventName])) {
            $this->listeners[$eventName]   = [];
            $this->listeners[$eventName][] = $listener;
            return 1;
        }
        return array_unshift($this->listeners[$eventName], $listener);
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
     * be differentiated from two lazy listeners.
     * 
     * * In order to lazy-load class listeners whose constructors don't specify
     * concrete class names we need a way to pass along dependency definitions
     * along with the class name strings. For this we use a simple array. The
     * issue then becomes differentiating in a list of multiple listeners passed
     * to `Mediator::pushAll` whether an array is a PHP callback or a string
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
        if (!($iterable instanceof \StdClass
            || is_array($iterable)
            || $iterable instanceof \Traversable))
        {
            throw new \InvalidArgumentException(
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
            $this->listeners = [];
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
     * Retrieve a list of all listened-for events in the queue
     * 
     * @return array Returns an array of listened-for events in the queue
     */
    public function keys()
    {
        return array_keys($this->listeners);
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
                    return $i+1;
                }
            }
        }
        return $count;
    }
}
