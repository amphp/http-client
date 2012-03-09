<?php

/**
 * Artax Bootstrapper Class File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax;
  
/**
 * Artax Bootstrapper Class
 * 
 * At present the only function this class serves is to inject the mediator
 * instance into the framework's fatal error handler without cluttering the
 * global namespace.
 * 
 * The Termination needs access to the Mediator to enable event-managed
 * handling for fatal shutdowns and uncaught exceptions (app.exception) and
 * normal shutdown events (app.tearDown).
 * 
 * @category Artax
 * @package  Core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
class Bootstrapper
{
    /**
     * An Termination (fatal or normal) Handler
     * @var Termination
     */
    protected $termination;
    
    /**
     * An event mediator instance
     * @var MediatorInterface
     */
    protected $mediator;
    
    /**
     * Constructor injects object dependencies
     * 
     * @param Termination $term The TerminationInstance
     * @param Mediator    $med  An event mediator object
     * 
     * @return void
     */
    public function __construct(Handlers\Termination $term, Events\Mediator $med)
    {
        $this->termination = $term;
        $this->mediator    = $med;
    }
    
    /**
     * Boot the event management "framework"
     * 
     * The only function served here is to inject the Termination instance
     * with the Mediator to allow event-managed handling of uncaught exceptions
     * and E_FATAL errors that can't be handled by the ErrorHandler.
     * 
     * More functionality may be added going forward.
     * 
     * @return Mediator Returns the mediator for application object injection
     */
    public function boot()
    {
        $this->termination->setMediator($this->mediator);
        return $this->mediator;
    }
}
