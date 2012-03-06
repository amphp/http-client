<?php

/**
 * NotifierInterface File
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
   * NotifierInterface
   * 
   * Defines an interface that objects can implement for communicating with the
   * event mediator.
   * 
   * @category   artax
   * @package    core
   * @subpackage events
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface NotifierInterface
  {
    /**
     * Notify the mediator of an event occurrence
     * 
     * @param string $eventName The event name
     */
    public function notify($eventName);
  }
}
