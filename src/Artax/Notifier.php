<?php

/**
 * Notifier Interface File
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
  
/**
 * Defines the public facing interface for event notifiers.
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Notifier
{
    /**
     * Notify listeners that the specified event has occurred
     * 
     * @param string $event The event that occurred
     */
    public function notify($event);
    
    /**
     * Iterates through the items in the order they are traversed, adding them
     * to the event queue found in the key.
     *
     * @param mixed $iterable The variable to loop through and add listeners
     */
    public function pushAll($iterable);
    
    /**
     * Connect a `$listener` to the end of the `$eventName` queue
     * 
     * @param string $eventName Event identifier name
     * @param mixed  $listener  Event listener
     * @param mixed  $lazyDef   Optional lazy injection definition array
     *                          or instance of ArrayAccess
     */
    public function push($eventName, $listener, $lazyDef);
    
    /**
     * Connect a `$listener` to the front of the `$eventName` queue
     * 
     * @param string $eventName Event identifier name
     * @param mixed  $listener  Event listener
     * @param mixed  $lazyDef   Optional lazy injection definition array
     *                          or instance of ArrayAccess
     */
    public function unshift($eventName, $listener, $lazyDef);
    
    /**
     * Remove the first `$listener` from the front of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name
     */
    public function shift($eventName);
    
    /**
     * Remove the last `$listener` from the end of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name
     */
    public function pop($eventName);
    
    /**
     * Clear all listeners from the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name
     */
    public function clear($eventName);
    
    /**
     * Retrieve a count of all listeners in the queue for a specific event
     * 
     * @param string $eventName Event identifier name
     */
    public function count($eventName);
    
    /**
     * Retrieve a list of all event listeners in the queue for an event
     */
    public function all($eventName);
    
    /**
     * Retrieve the first event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     */
    public function first($eventName);
    
    /**
     * Retrieve the last event listener in the queue for the specified event
     * 
     * @param string $eventName Event identifier name
     */
    public function last($eventName);
    
    /**
     * Retrieve a list of all listened-for events in the queue
     */
    public function keys();
}
