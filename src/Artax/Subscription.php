<?php

namespace Artax;

class Subscription {
    
    private $subject;
    private $observerCallbacks = array();
    private $isEnabled = TRUE;
    
    function __construct(Observable $subject, array $observerCallbacks) {
        $this->subject = $subject;
        $this->assignCallbacks($observerCallbacks);
    }
    
    private function assignCallbacks(array $observerCallbacks) {
        if (empty($observerCallbacks)) {
            throw new \LogicException(
                'No subscription observers specified'
            );
        }
        
        foreach ($observerCallbacks as $event => $callback) {
            if (is_callable($callback)) {
                $this->observerCallbacks[$event] = $callback;
            } else {
                throw new \RuntimeException(
                    'Invalid subscription callback'
                );
            }
        }
    }
    
    function enable() {
        $this->isEnabled = TRUE;
    }
    
    function disable() {
        $this->isEnabled = FALSE;
    }
    
    function cancel() {
        $this->subject->unsubscribe($this);
    }
    
    function modify(array $observerCallbacks) {
        $this->assignCallbacks($observerCallbacks);
    }
    
    function replace(array $observerCallbacks) {
        $this->observerCallbacks = array();
        $this->assignCallbacks($observerCallbacks);
    }
    
    function __invoke($event, $data = NULL) {
        if ($this->isEnabled && !empty($this->observerCallbacks[$event])) {
            call_user_func($this->observerCallbacks[$event], $data);
        }
    }
    
}

