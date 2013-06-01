<?php

namespace Artax;

/**
 * An alternative to SplObserver introducing the expectation that Observables may broadcast any
 * number of discrete events instead of a single, monolithic "update" notification.
 */
interface Observable {
    
    const READY = 'ready';
    const DATA = 'data';
    const SEND = 'send';
    const DRAIN = 'drain';
    const ERROR = 'error';
    const DONE = 'done';
    
    /**
     * Attach an array of event Subscribers
     * 
     * @param array $listeners A key-value array mapping event names to callable listeners
     * @param bool $unsubscribeOnError Remove this subscription of the Observable encounters an error
     * @throws \Ardent\FunctionException On invalid Subscriber (listener) callback(s)
     * @return Subscription
     */
    function subscribe(array $listeners, $unsubscribeOnError = TRUE);
    
    /**
     * Remove the specified Subscription from the Observable
     * 
     * @param Subscription $subscription
     * @return void
     */
    function unsubscribe(Subscription $subscription);
    
    /**
     * Clear all existing Subscription instances for this Observable
     * 
     * @return void
     */
    function unsubscribeAll();
    
    /**
     * Notify subscribers of an event's occurrence
     * 
     * @param string $event
     * @param mixed $data
     * @return void
     */
    function notify($event, $data = NULL);
    
}

