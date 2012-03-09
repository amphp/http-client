<?php

/**
 * NotifierInterface File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Events;
  
/**
 * NotifierInterface
 * 
 * Defines an interface that objects can implement for communicating with the
 * event mediator.
 * 
 * @category   Artax
 * @package    Events
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
