<?php

use Artax\Observation,
    Artax\Observable,
    Artax\ObservableSubject;

class ObservableSubjectTest extends PHPUnit_Framework_TestCase {
    
    function testUnsubscribe() {
        $listener = function() use (&$counter) { $counter++; };
        
        $subject = new ObservableSubjectTestStub;
        $event = 'event';
        $observation = $subject->addObservation([
            $event => $listener
        ]);
        
        $subject->removeObservation($observation);
    }
    
    function testRemoveAllObservations() {
        $counter = 0;
        $listener = function() use (&$counter) { $counter++; };
        
        $subject = new ObservableSubjectTestStub;
        $event = 'event';
        $subscription = $subject->addObservation([
            $event => $listener
        ]);
        
        $subject->notify($event);
        $this->assertEquals(1, $counter);
        $subject->removeAllObservations();
        $subject->notify($event);
        $this->assertEquals(1, $counter);
    }
    
}

class ObservableSubjectTestStub implements Observable {
    use ObservableSubject;
    
    function notify($event, $data = NULL) {
        $this->notifyObservations($event, $data);
    }
}
