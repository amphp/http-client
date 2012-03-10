<?php

/**
 * Artax NotifierTrait File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Events
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
 * @package    Events
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
     * If no data arguments are passed the current object instance will be sent
     * to the mediator as the sole notification data parameter.
     * 
     * @param string $event The event that occurred
     * 
     * @return int Returns the number of listeners invoked for the event.
     */
    public function notify($event)
    {
        if (NULL !== $this->mediator) {
            if (func_num_args() == 1) {
                return $this->mediator->notify($event, $this);
            } else {
                $args = func_get_args();
                return call_user_func_array([$this->mediator, 'notify'], $args);
            }
        }
    }
}
