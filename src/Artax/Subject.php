<?php

namespace Artax;

trait Subject {
    
    private $subscribers;
    
    function subscribe(array $eventListenerMap, $unsubscribeOnError = TRUE) {
        $this->subscribers = $this->subscribers ?: new \SplObjectStorage;
        $subscription = new Subscription($this, $eventListenerMap, $unsubscribeOnError);
        $this->subscribers->attach($subscription);
        
        return $subscription;
    }
    
    function unsubscribe(Subscription $subscription) {
        $this->subscribers->detach($subscription);
    }
    
    function unsubscribeAll() {
        $this->subscribers = new \SplObjectStorage;
    }
    
    function notify($event, $data = NULL) {
        $this->subscribers = $this->subscribers ?: new \SplObjectStorage;
        
        foreach ($this->subscribers as $subscription) {
            call_user_func($subscription, $event, $data);
        }
    }
}
