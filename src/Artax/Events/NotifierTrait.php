<?php

/**
 * Artax NotifierTrait File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Events;
  
/**
 * NotifierTrait
 * 
 * Specifies functionality for implementing the NotifierInterface. Classes can
 * use this trait to easily interact with an event mediator instance.
 * 
 * @category   Artax
 * @package    core
 * @subpackage events
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
trait NotifierTrait
{
    /**
     * A Mediator object instance
     * 
     * This property should be specifically injected into the class using the 
     * trait via the class constructor or a relevant setter method.
     * 
     * @var MediatorInterface
     */
    protected $mediator;
    
    /**
     * Notify the mediator of an event occurrence
     * 
     * If no data parameter is passed the current object instance will be sent
     * to the mediator as the notification data parameter.
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
