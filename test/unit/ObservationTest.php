<?php

use Artax\Observation,
    Artax\Observable,
    Artax\ObservableSubject;

class ObservationTest extends PHPUnit_Framework_TestCase {
    
    function testListenerInvocation() {
        $counter = 0;
        $listener = function() use (&$counter) { $counter++; };
        
        $subject = new ObservationTestSubjectStub;
        $event = 'event';
        $observation = new Observation($subject, [
            $event => $listener
        ]);
        
        $observation->__invoke($event);
        
        $this->assertEquals(1, $counter);
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    function testObservationThrowsOnEmptyListenerArray() {
        $subject = new ObservationTestSubjectStub;
        $observation = new Observation($subject, array());
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    function testObservationThrowsOnNonCallableListener() {
        $subject = new ObservationTestSubjectStub;
        $observation = new Observation($subject, array(
            'event' => 'this is supposed to be callable'
        ));
    }
    
    function testDisableAndEnable() {
        $tracker = 0;
        $listener = function() use (&$tracker) {
            $tracker++;
        };
        
        $subject = new ObservationTestSubjectStub;
        $observation = new Observation($subject, array(
            'event' => $listener
        ));
        
        $observation('event');
        $observation('event');
        $this->assertEquals(2, $tracker);
        
        $observation->disable();
        
        $observation('event');
        $this->assertEquals(2, $tracker);
        
        $observation->enable();
        
        $observation('event');
        $this->assertEquals(3, $tracker);
    }
    
    function testCancel() {
        $tracker = 0;
        $listener = function() use (&$tracker) {
            $tracker++;
        };
        
        $subject = new ObservationTestSubjectStub;
        $observation = new Observation($subject, array(
            'event' => $listener
        ));
        
        $observation('event');
        $observation('event');
        $this->assertEquals(2, $tracker);
        
        $observation->cancel();
        
        $subject->notify('event');
        $this->assertEquals(2, $tracker);
    }
    
    function testModify() {
        $tracker = 0;
        $listener1 = function() use (&$tracker) {
            $tracker++;
        };
        $listener2 = function() use (&$tracker) {
            $tracker += 10;
        };
        
        $subject = new ObservationTestSubjectStub;
        $observation = new Observation($subject, array(
            'event1' => $listener1,
            'event2' => $listener2
        ));
        
        $observation('event1');
        $this->assertEquals(1, $tracker);
        
        $observation->modify([
            'event1' => $listener2
        ]);
        
        $observation('event1');
        $this->assertEquals(11, $tracker);
    }
    
    function testReplace() {
        $tracker = 0;
        $listener1 = function() use (&$tracker) {
            $tracker++;
        };
        $listener2 = function() use (&$tracker) {
            $tracker--;
        };
        
        $subject = new ObservationTestSubjectStub;
        $observation = new Observation($subject, array(
            'event1' => $listener1,
            'event2' => $listener2
        ));
        
        $observation('event1');
        $this->assertEquals(1, $tracker);
        $observation('event2');
        $this->assertEquals(0, $tracker);
        
        $observation->replace([
            'event1' => $listener2
        ]);
        
        $observation('event1');
        $this->assertEquals(-1, $tracker);
    }
    
}

class ObservationTestSubjectStub implements Observable {
    use ObservableSubject;
    
    function notify($event, $data = NULL) {
        $this->notifyObservations($event, $data);
    }
}
