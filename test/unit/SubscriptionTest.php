<?php

use Artax\Subscription,
    Artax\Observable,
    Artax\Subject;

class SubscriptionTest extends PHPUnit_Framework_TestCase {
    
    function testListenerInvocation() {
        $counter = 0;
        $listener = function() use (&$counter) { $counter++; };
        
        $subject = new SubscriptionTestSubjectStub;
        $event = 'event';
        $subscription = new Subscription($subject, [
            $event => $listener
        ]);
        
        $subscription->__invoke($event);
        
        $this->assertEquals(1, $counter);
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    function testSubscriptionThrowsOnEmptyListenerArray() {
        $subject = new SubscriptionTestSubjectStub;
        $subscription = new Subscription($subject, array());
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    function testSubscriptionThrowsOnNonCallableListener() {
        $subject = new SubscriptionTestSubjectStub;
        $subscription = new Subscription($subject, array(
            'event' => 'this is supposed to be callable'
        ));
    }
    
    function testDisableAndEnable() {
        $tracker = 0;
        $listener = function() use (&$tracker) {
            $tracker++;
        };
        
        $subject = new SubscriptionTestSubjectStub;
        $subscription = new Subscription($subject, array(
            'event' => $listener
        ));
        
        $subscription('event');
        $subscription('event');
        $this->assertEquals(2, $tracker);
        
        $subscription->disable();
        
        $subscription('event');
        $this->assertEquals(2, $tracker);
        
        $subscription->enable();
        
        $subscription('event');
        $this->assertEquals(3, $tracker);
    }
    
    function testCancel() {
        $tracker = 0;
        $listener = function() use (&$tracker) {
            $tracker++;
        };
        
        $subject = new SubscriptionTestSubjectStub;
        $subscription = new Subscription($subject, array(
            'event' => $listener
        ));
        
        $subscription('event');
        $subscription('event');
        $this->assertEquals(2, $tracker);
        
        $subscription->cancel();
        
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
        
        $subject = new SubscriptionTestSubjectStub;
        $subscription = new Subscription($subject, array(
            'event1' => $listener1,
            'event2' => $listener2
        ));
        
        $subscription('event1');
        $this->assertEquals(1, $tracker);
        
        $subscription->modify([
            'event1' => $listener2
        ]);
        
        $subscription('event1');
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
        
        $subject = new SubscriptionTestSubjectStub;
        $subscription = new Subscription($subject, array(
            'event1' => $listener1,
            'event2' => $listener2
        ));
        
        $subscription('event1');
        $this->assertEquals(1, $tracker);
        $subscription('event2');
        $this->assertEquals(0, $tracker);
        
        $subscription->replace([
            'event1' => $listener2
        ]);
        
        $subscription('event1');
        $this->assertEquals(-1, $tracker);
    }
    
}

class SubscriptionTestSubjectStub implements Observable {
    use Subject;
}
