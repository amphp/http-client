<?php
/**
 * Mediator Interface File
 * 
 * @category    Artax
 * @package     Events
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Events;
  
/**
 * Defines the facing interface for event mediators.
 * 
 * @category    Artax
 * @package     Events
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Mediator {
    
    /**
     * Notify listeners that the specified event has occurred
     * 
     * @param string $event The event that occurred
     */
    function notify($event);
    
    /**
     * Iterates through the items in the order they are traversed, adding them
     * to the event queue found in the key.
     *
     * @param mixed $iterable The variable to loop through and add listeners
     */
    function pushAll($iterable);
    
    /**
     * Connect a `$listener` to the end of the `$eventName` queue
     * 
     * @param string $eventName Event identifier name
     * @param mixed  $listener  Event listener
     */
    function push($eventName, $listener);
    
    /**
     * Connect a `$listener` to the front of the `$eventName` queue
     * 
     * @param string $eventName Event identifier name
     * @param mixed  $listener  Event listener
     */
    function unshift($eventName, $listener);
    
    /**
     * Remove the first `$listener` from the front of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name
     */
    function shift($eventName);
    
    /**
     * Remove the last `$listener` from the end of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name
     */
    function pop($eventName);
    
    /**
     * Clear all listeners from the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name
     */
    function clear($eventName);
    
    /**
     * Retrieve a count of all listeners in the queue for a specific event
     * 
     * @param string $eventName Event identifier name
     */
    function count($eventName);
    
    /**
     * Retrieve a list of all event listeners in the queue for an event
     */
    function all($eventName);
    
    /**
     * Retrieve the first event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     */
    function first($eventName);
    
    /**
     * Retrieve the last event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     */
    function last($eventName);
    
    /**
     * Retrieve a list of all listened-for events in the queue
     */
    function keys();
    
    /**
     * Get the total number of listeners that have been invoked for an event
     * 
     * @param string $eventName
     * 
     * @return int Returns total invocation count for the specified event.
     */
    function countInvocations($eventName);
    
    /**
     * Get the total number of times an event has been broadcast/notified
     * 
     * @param string $eventName
     * 
     * @return int Returns total notification count for the specified event.
     */
    function countNotifications($eventName);
    
}
