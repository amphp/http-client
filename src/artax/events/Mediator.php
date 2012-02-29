<?php

/**
 * Artax Mediator Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\events {
  
  /**
   * Mediator Class
   * 
   * @category   artax
   * @package    core
   * @subpackage events
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
     * Class constructor
     * 
     * @param array $listeners An optional array of listeners to populate the 
     *                         object upon instantiation.
     * 
     * @return void
     */
    public function __construct(array $listeners=[])
    {
      $this->listeners = [];
      if ($listeners) {
        foreach ($listeners as $listener) {
          $this->push($listener[0], $listener[1]);
        }
      }
    }
    
    /**
     * Connect a `$listener` to end of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  Callable event listener
     * 
     * @return Returns the number of listeners in the queue for the specified event
     */
    public function push($eventName, Callable $listener)
    {
      if ( ! isset($this->listeners[$eventName])) {
        $this->listeners[$eventName]   = [];
        $this->listeners[$eventName][] = $listener;
        return 1;
      } else {
        return array_push($this->listeners[$eventName], $listener);
      }
    }
    
    /**
     * Connect a `$listener` to the beginning of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * @param mixed  $listener  Event listener
     * 
     * @return int Returns the new number of listeners for the specified event.
     */
    public function unshift($eventName, Callable $listener)
    {
      if ( ! isset($this->listeners[$eventName])) {
        $this->listeners[$eventName]   = [];
      }
      return array_unshift($this->listeners[$eventName], $listener);
    }
    
    /**
     * Remove the first `$listener` from the start of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * 
     * @return mixed Callable listener on success or `NULL` if no listeners
     *               were found for the specified event
     */
    public function shift($eventName)
    {
      if (isset($this->listeners[$eventName])) {
        return array_shift($this->listeners[$eventName]);
      }
      return NULL;
    }
    
    /**
     * Remove the last `$listener` from the end of the `$eventName` event queue
     * 
     * @param string $eventName Event identifier name to listen for
     * 
     * @return mixed Callable listener on success or `NULL` if no listeners
     *               were found for the specified event
     */
    public function pop($eventName)
    {
      if (isset($this->listeners[$eventName])) {
        return array_pop($this->listeners[$eventName]);
      }
      return NULL;
    }
    
    /**
     * Clear all listeners from the `$eventName` event queue
     * 
     * Clears all listeners for the specified event. If an empty value is passed
     * for the `$eventName`, all listeners for all events will be cleared.
     * 
     * @param string $eventName Event identifier name
     * 
     * @return void
     */
    public function clear($eventName=NULL)
    {
      if ($eventName && isset($this->listeners[$eventName])) {
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
     * @return int Returns a count of listeners in the queue for the specified event
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
     * Retrieve a list of all event listeners in the queue
     * 
     * @param string $eventName An optional event name to filter returned listeners
     *                          to a specific event name.
     * 
     * @return array Returns an array of all event listeneres
     */
    public function all($eventName=NULL)
    {
      if ($eventName && isset($this->listeners[$eventName])) {
        return $this->listeners[$eventName];
      } else {
        return $this->listeners;
      }
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
      if (isset($this->listeners[$eventName][0])) {
        return $this->listeners[$eventName][0];
      }
      return NULL;
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
      if (isset($this->listeners[$eventName])
        && $count = count($this->listeners[$eventName])) {
        return $this->listeners[$eventName][$count-1];
      } else {
        return NULL;
      }
    }
    
    /**
     * Notify listeners that the `$eventName` event has occurred
     * 
     * Listeners are treated as a queue in which the first registered listener
     * executes first, continuing down the queue until a listener returns `FALSE`
     * or the end of the queue is reached.
     * 
     * @param string $eventName Event identifier name
     * @param string $data      Optional data to pass to listeners
     * 
     * @return int Count of listeners invoked as a result of notification
     */
    public function notify($eventName, $data=NULL)
    {
      $execCount = 0;
      if (isset($this->listeners[$eventName])) {
        foreach ($this->listeners[$eventName] as $callable) {
          ++$execCount;
          if ($callable($data) === FALSE) {
            return $execCount;
          }
        }
      }
      return $execCount;
    }
  }
}
