<?php

/**
 * Artax NotifierInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {
  
  /**
   * NotifierInterface
   * 
   * Defines an interface that objects must implement to communicate with the
   * core event mediator.
   * 
   * @category   artax
   * @package    core
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
