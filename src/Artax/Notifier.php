<?php

/**
 * Artax Notifier Class File
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
    ArrayAccess,
    Traversable,
    StdClass;

/**
 * A central transit hub for application event broadcasting
 * 
 * For advanced Notifier usage including lazy-loading class event listeners,
 * check out the relevant wiki entry:
 * 
 * https://github.com/rdlowrey/Artax/wiki/Event-Management
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 */
class Notifier implements Mediator {
    
    /**
     * Tracks the number of times each event is broadcast
     * @var array
     */
    private $eventBroadcastCounts = array();
    
    /**
     * Tracks the number of listener invocations per event
     * @var array
     */
    private $listenerInvocationCounts = array();
    
    /**
     * A dictionary mapping event names to queued listeners
     * @var array
     */
    private $listeners = array();
    
    /**
     * Dependency provider for class listener lazy-loading
     * @var InjectionContainer
     */
    private $provider;
    
    /**
     * @param InjectionContainer $provider A dependency injection container
     * @return void
     */
    public function __construct(InjectionContainer $provider) {
    
        $this->provider = $provider;
    }
    
    /**
     * Notify listeners that an event has occurred
     * 
     * Listeners act as a queue and are invoked in order until a listener in the
     * chain returns `false` or the end of the queue is reached.
     * 
     * @param string $event The event to broadcast
     * 
     * @return int Returns a count of listeners invoked for this event broadcast
     * @throws BadListenerException
     */
    public function notify($eventName) {
        
        $this->incrementEventBroadcastCount($eventName);
        
        $args = func_get_args();
        array_shift($args);
        
        $listenerCount = $this->count($eventName);
        $invocationCount = 0;
        
        for ($queuePos = 0; $queuePos < $listenerCount; $queuePos++) {
        
            try {
                $listener = $this->getCallableListenerFromQueue($eventName, $queuePos);
            } catch (ProviderDefinitionException $e) {
                $className = $this->listeners[$eventName][$queuePos];
                throw new BadListenerException(
                    "Invalid class listener ($className) specified in the `$eventName` " .
                    "queue at position $queuePos. Auto-instantiation failed with the " .
                    'following message: ' . $e->getMessage()
                );
            }
            
            if (!is_callable($listener) && is_object($listener)) {
                throw new BadListenerException(
                    "Invalid listener specified in the `$eventName` queue at position " .
                    "$queuePos: object of type ".get_class($listener).' is not callable'
                );
            }
            
            $this->incrementListenerInvocationCount($eventName);
            $result = $args ? call_user_func_array($listener, $args) : $listener();
            ++$invocationCount;
            if (false === $result) {
                break;
            }
        }
        
        return $invocationCount;
    }
    
    /**
     * Connect a listener to the end of the specified event queue
     * 
     * To enable listener lazy-loading, all string listeners are assumed to be 
     * class names and will be instantiated using the dependency provider. This
     * means that global function names and static class methods (in string form)
     * cannot be attached.
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  A valid callable or string class name
     * @param mixed  $lazyDef   An optional array or ArrayAccess specifying a
     *                          custom injection definition for lazy-loading
     * 
     * @return int Returns the new number of listeners in the queue for the
     *             specified event.
     * 
     * @throws LogicException On non-callable, non-string $listener parameter
     * @uses Notifier::attach
     */
    public function push($eventName, $listener, $lazyDef = null) {
        
        $isString = is_string($listener);
        
        if (!($isString || is_callable($listener))) {
            throw new InvalidArgumentException(
                'Argument 2 passed to ' . get_class($this) .'::push must be a valid ' . 
                'callable or string class name'
            );
        }
        
        if (null !== $lazyDef && !($lazyDef instanceof ArrayAccess || is_array($lazyDef))) {
            throw new InvalidArgumentException(
                'Argument 3 passed to ' . get_class($this) .'::push must be an array ' .
                'or object implementing ArrayAccess'
            );
        }
        
        $listener = $isString && null !== $lazyDef ? array($listener, $lazyDef) : $listener;
        
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
            || $iterable instanceof Traversable
            || is_array($iterable)
        )) {
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
     * Attach an event listener to the front of the event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  A valid callable or string class name
     * @param mixed  $lazyDef   An optional array or ArrayAccess specifying a
     *                          custom injection definition for lazy-loading
     * 
     * @return int Returns the new number of listeners in the event queue
     * @throws InvalidArgumentException
     */
    public function unshift($eventName, $listener, $lazyDef = null) {
        
        $isString = is_string($listener);
        
        if (!($isString || is_callable($listener))) {
            throw new InvalidArgumentException(
                'Argument 2 passed to ' . get_class($this) .'::unshift must be a valid ' . 
                'callable or string class name'
            );
        }
        
        if (null !== $lazyDef && !($lazyDef instanceof ArrayAccess || is_array($lazyDef))) {
            throw new InvalidArgumentException(
                'Argument 3 passed to ' . get_class($this) .'::unshift must be an array ' .
                'or object implementing ArrayAccess'
            );
        }
        
        $listener = $isString && null !== $lazyDef ? array($listener, $lazyDef) : $listener;
        
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = array();
            $this->listeners[$eventName][] = $listener;
            return 1;
        }
        
        return array_unshift($this->listeners[$eventName], $listener);
    }
    
    /**
     * Remove a listener from the front of the specified event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * 
     * @return mixed Returns shifted listener on success or null if no listeners
     *               were found for the specified event.
     */
    public function shift($eventName) {
        
        if (isset($this->listeners[$eventName])) {
            return array_shift($this->listeners[$eventName]);
        }
        
        return null;
    }
    
    /**
     * Remove the last listener from the end of the specified event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * 
     * @return mixed Returns popped listener on success or `null` if no listeners
     *               were found for the specified event.
     */
    public function pop($eventName) {
    
        if (isset($this->listeners[$eventName])) {
            return array_pop($this->listeners[$eventName]);
        }
        
        return null;
    }
    
    /**
     * Retrieve a list of all listeners queued for the specified event
     * 
     * @param string $eventName The event for which listeners should be returned
     * 
     * @return array Returns an array of queued listeners for the specified event
     *               or null if no listeners are registered for the event.
     */
    public function all($eventName) {
    
        return $this->count($eventName) ? $this->listeners[$eventName] : null;
    }
    
    /**
     * Retrieve the first event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     * 
     * @return callable Returns the first event listener in the queue or `null`
     *                  if none exist for the specified event.
     */
    public function first($eventName) {
    
        return $this->count($eventName) ? $this->listeners[$eventName][0] : null;
    }
    
    /**
     * Retrieve the last event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     * 
     * @return callable Returns the last event listener in the queue or `null`
     *                  if none exist for the specified event.
     */
    public function last($eventName) {
    
        return ($count = $this->count($eventName)) ? $this->listeners[$eventName][$c-1] : null;
    }
    
    /**
     * Retrieve a list of all listened-for events
     * 
     * @return array Returns an array of listened-for events
     */
    public function keys() {
        
        return array_keys($this->listeners);
    }
    
    /**
     * Retrieve a count of all listeners in the queue for a specific event
     * 
     * @param string $eventName Event identifier name
     * 
     * @return int Returns a count of queued listeners for the specified event
     */
    public function count($eventName) {
        
        return isset($this->listeners[$eventName]) ? count($this->listeners[$eventName]) : 0;
    }
    
    /**
     * Clear all listeners from the specified event queue
     * 
     * Clears all listeners for the specified event. If an empty parameter value
     * is passed for the `$eventName`, *all* listeners will be cleared for *all*
     * events.
     * 
     * @param string $eventName Event name
     * 
     * @return void
     */
    public function clear($eventName = null) {
        
        if (null !== $eventName && isset($this->listeners[$eventName])) {
            unset($this->listeners[$eventName]);
        } else {
            $this->listeners = array();
        }
    }
    
    /**
     * Get the total number of listeners that have been invoked for an event
     * 
     * @param string $eventName The name of the event for which an invocation count
     *                      is requested. If null is passed, the total count
     *                      of listener invocations for all events will be
     *                      returned. If a non-existent event is requested,
     *                      zero is returned.
     * 
     * @param int Returns the count of all invocations for the given event.
     */
    public function getInvocationCount($eventName = null) {
        
        if (null === $eventName) {
            return array_sum($this->listenerInvocationCounts);
        } elseif (isset($this->listenerInvocationCounts[$eventName])) {
            return $this->listenerInvocationCounts[$eventName];
        } else {
            return 0;
        }
    }
    
    /**
     * Get the total number of times an event has been broadcast/notified
     * 
     * @param string $eventName The name of the event for which an notification 
     *                      count is requested. If null is passed, the total 
     *                      count of listener notifications for all events 
     *                      will be returned. If a non-existent event is 
     *                      requested, zero is returned.
     * 
     * @param int Returns the count of all notifications for the given event.
     */
    public function getNotificationCount($eventName = null) {
    
        if (null === $eventName) {
            return array_sum($this->eventBroadcastCounts);
        } elseif (isset($this->eventBroadcastCounts[$eventName])) {
            return $this->eventBroadcastCounts[$eventName];
        } else {
            return 0;
        }
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    private function incrementEventBroadcastCount($eventName) {
        
        if (!isset($this->eventBroadcastCounts[$eventName])) {
            $this->eventBroadcastCounts[$eventName] = 0;
        }
        ++$this->eventBroadcastCounts[$eventName];
    }
    
    /**
     * @param string $eventName
     * @return void
     */
    private function incrementListenerInvocationCount($eventName) {
        
        if (!isset($this->listenerInvocationCounts[$eventName])) {
            $this->listenerInvocationCounts[$eventName] = 0;
        }
        ++$this->listenerInvocationCounts[$eventName];
    }
    
    /**
     * To differentiate between string class names with injection definitions
     * and valid PHP callback arrays we check the second array element. All
     * lazy listeners are stored inside an array alongside an injection definition
     * array (possibly empty). Meanwhile, valid PHP array callbacks will never 
     * store an array as their second data parameter.
     * 
     * @param string $eventName
     * @param int $queuePos
     * @return mixed Returns a callable event listener
     * @throws ProviderDefinitionException
     */
    private function getCallableListenerFromQueue($eventName, $queuePos) {
        
        if (is_string($this->listeners[$eventName][$queuePos])) {
            $func = $this->provider->make($this->listeners[$eventName][$queuePos]);
        } elseif (is_array($this->listeners[$eventName][$queuePos])
            && isset($this->listeners[$eventName][$queuePos][1])
            && is_array($this->listeners[$eventName][$queuePos][1])
        ) {
            $func = $this->provider->make($this->listeners[$eventName][$queuePos][0],
                $this->listeners[$eventName][$queuePos][1]
            );
        } else {
            $func = $this->listeners[$eventName][$queuePos];
        }
        
        return $func;
    }
}
