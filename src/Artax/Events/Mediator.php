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
 * The Artax Mediator exposes a simple interface for attaching a chain of listeners
 * to a managed event queue. You can attach any valid PHP callable to an event
 * queue for processing or specify a DotNotation string to utilize the Mediator's
 * built-in lazy-loading functionality.
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
 * according to their position in the queue for any notified event. Since we pushed
 * the "My first mediated event!" listener first, it's the first listener executed
 * when the object is notified of the `my_event_name` event.
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
 * For advanced mediator usage, check out the wiki entry over at github:
 * https://github.com/rdlowrey/Artax/wiki/Event-Management
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
    protected $listeners;
    
    /**
     * Dependency provider for listener lazy-loading
     * @var Provider
     */
    protected $deps;
    
    /**
     * Injects dependency provider for lazy-loading object listeners
     * 
     * If no dependency provider is specified, a factory is used to create one.
     * 
     * @param Artax\Ioc\Provider $deps A dependency provider instance for
     *                                    lazy-loading object listeners
     * 
     * @return void
     */
    public function __construct(\Artax\Ioc\Provider $deps = NULL)
    {
        $this->deps = $deps ?: (new \Artax\Ioc\ProviderFactory)->make();
        $this->listeners = [];
    }
    
    /**
     * Connect a listener to the end of the specified event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  The event listener. Must be a valid callable or
     *                          a dot-notation class name
     * 
     * @return int Returns the new number of listeners in the queue for the
     *             specified event.
     * 
     * @throws LogicException when $listener is not an array or Traversable, or 
     *         if it is not callable.
     */
    public function push($eventName, $listener)
    {
        if ($listener instanceof \StdClass
            || is_array($listener)
            || $listener instanceof \Traversable)
        {
            foreach ($listener as $listenerItem) {
                $this->push($eventName, $listenerItem);
            }
            return $this->count($eventName);
            
        } elseif (!(is_callable($listener) || is_string($listener))) {
            throw new \InvalidArgumentException(
                'Argument 2 for ' . get_class($this)
                . '::push must be a valid callable or dot-notation string'
            );
        } else {
            if ( ! isset($this->listeners[$eventName])) {
                $this->listeners[$eventName]   = [];
                $this->listeners[$eventName][] = $listener;
                return 1;
            }
            return array_push($this->listeners[$eventName], $listener);
        }
    }
    
    /**
     * Iterates through the items in the order they are traversed, adding them
     * to the event queue found in the key.
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
                'Argument 1 passed to addAll must be an array, Traversable '
                . 'or StdClass instance'
            );
        }
        $addedListenerCount = 0;
        foreach ($iterable as $event => $value) {
            $addedListenerCount += $this->push($event, $value);
        }
        return $addedListenerCount;
    }
    
    /**
     * Connect an event listener to the front of the specified event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  The event listener. Must be a valid callable or
     *                          a dot-notation class name
     * 
     * @return int Returns the new number of listeners in the queue for the
     *             specified event.
     */
    public function unshift($eventName, $listener)
    {
        if (!(is_callable($listener) || is_string($listener))) {
            throw new \InvalidArgumentException(
                'Argument 2 for ' . get_class($this)
                . '::unshift must be a valid callable or dot-notation string'
            );
        } else {
            if ( ! isset($this->listeners[$eventName])) {
                $this->listeners[$eventName] = [];
            }
            return array_unshift($this->listeners[$eventName], $listener);
        }
    }
    
    /**
     * Remove the first listener from the front of the specified event queue
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
        return ($c = $this->count($eventName))
            ? $this->listeners[$eventName][$c-1]
            : NULL;
    }
    
    /**
     * Notify listeners that an event has occurred
     * 
     * Listeners are treated as a queue in which the first registered listener
     * executes first, continuing down the queue until a listener returns `FALSE`
     * or the end of the queue is reached.
     * 
     * @param string $event The event that occurred
     * 
     * @return int Returns a count of listeners invoked for the notified event
     */
    public function notify($event)
    {
        if ($c = $this->count($event)) {
            if (1 == func_num_args()) {
                $args = NULL;
            } else {
                $args = func_get_args();
                array_shift($args);
            }
            for ($i=0; $i<$c; $i++) {
                if (is_string($this->listeners[$event][$i])) {
                    $func = $this->deps->make($this->listeners[$event][$i]);
                } else {
                    $func = $this->listeners[$event][$i];
                }
                $result = $args ? call_user_func_array($func, $args) : $func();
                if ($result === FALSE) {
                    return $i + 1;
                }
            }
        }
        return $c;
    }
}
