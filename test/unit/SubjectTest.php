<?php

use Artax\Subscription,
    Artax\Observable,
    Artax\Subject;

class SubjectTest extends PHPUnit_Framework_TestCase {
    
    function testUnsubscribe() {
        $listener = function() use (&$counter) { $counter++; };
        
        $subject = new SubjectTestSubjectStub;
        $event = 'event';
        $subscription = $subject->subscribe([
            $event => $listener
        ]);
        
        $subject->unsubscribe($subscription);
    }
    
    function testUnsubscribeAll() {
        $counter = 0;
        $listener = function() use (&$counter) { $counter++; };
        
        $subject = new SubjectTestSubjectStub;
        $event = 'event';
        $subscription = $subject->subscribe([
            $event => $listener
        ]);
        
        $subject->notify($event);
        $this->assertEquals(1, $counter);
        $subject->unsubscribeAll();
        $subject->notify($event);
        $this->assertEquals(1, $counter);
    }
    
}

class SubjectTestSubjectStub implements Observable {
    use Subject;
}
