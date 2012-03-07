<?php

/**
 * Artax MediatorInterface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\events;
  
/**
 * MediatorInterface
 * 
 * @category   artax
 * @package    core
 * @subpackage events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface MediatorInterface
{
    /**
     * Notify listeners that the event `$eventName` has occurred
     * 
     * @param string $eventName Event identifier name
     */
    public function notify($eventName);
    
    /**
     * Connect a `$listener` to the end of the `$eventName` queue
     * 
     * @param string $eventName Event identifier name
     * @param mixed  $listener  Event listener
     */
    public function push($eventName, callable $listener, $rebind);
    
    /**
     * Connect a `$listener` to the front of the `$eventName` queue
     * 
     * @param string $eventName Event identifier name
     * @param mixed  $listener  Event listener
     */
    public function unshift($eventName, callable $listener, $rebind);
    
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
