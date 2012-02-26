<?php

/**
 * Artax NotifierTrait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * NotifierTrait
   * 
   * Specifies functionality for implementing the NotifierInterface.
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait NotifierTrait
  {
    /**
     * @var Mediator
     */
    protected $mediator;
    
    /**
     * Notify the mediator of an event occurrence
     * 
     * If no data parameter is passed the current object instance will be sent
     * as the notification data parameter.
     * 
     * @param string $eventName The event name
     * @param mixed  $data      Data to send with the notification
     */
    public function notify($eventName, $data=NULL)
    {
      $data = NULL === $data ? $this : $data;
      return  NULL === $this->mediator
        ? NULL
        : $this->mediator->notify($eventName, $data);
    }
  }
}
