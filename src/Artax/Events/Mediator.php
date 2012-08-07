<?php

namespace Artax\Events;
  
interface Mediator {
    
    /**
     * Notify listeners that the specified event has occurred
     * 
     * @param string $eventName
     */
    function notify($eventName);
    
    /**
     * Attach a listener to the end of the specified event queue
     * 
     * @param string $eventName
     * @param mixed  $listener
     */
    function push($eventName, $listener);
    
    /**
     * Pushes multiple event listeners by event-listener key-value pairs
     *
     * @param mixed $iterable An iterable key-value map linking events to listeners
     */
    function pushAll($iterable);
    
    /**
     * Attach a listener to the front of the specified event queue
     * 
     * @param string $eventName
     * @param mixed  $listener
     */
    function unshift($eventName, $listener);
    
    /**
     * Remove and return the first registered listener from the specified event queue
     * 
     * @param string $eventName
     */
    function shift($eventName);
    
    /**
     * Remove and return the last listener from the specified event queue
     * 
     * @param string $eventName
     */
    function pop($eventName);
    
    /**
     * Clear all listeners from the specified event queue
     * 
     * @param string $eventName
     */
    function clear($eventName);
    
    /**
     * Retrieve a count of all listeners in the specified event queue
     * 
     * @param string $eventName
     */
    function count($eventName);
    
    /**
     * Retrieve all event listeners in the specified event queue
     */
    function all($eventName);
    
    /**
     * Retrieve the first listener in the specified event queue
     * 
     * @param string $eventName
     */
    function first($eventName);
    
    /**
     * Retrieve the last event listener in the specified event queue
     * 
     * @param string $eventName
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
     */
    function getInvocationCount($eventName);
    
    /**
     * Get the total number of times an event has been broadcast (notified)
     * 
     * @param string $eventName
     */
    function getBroadcastCount($eventName);
    
    /**
     * Access information about the most recently modified queue and the action taken to modify it
     */
    function getLastQueueDelta();
    
}
