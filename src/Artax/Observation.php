<?php

namespace Artax;

class Observation {

    private $subject;
    private $callbacks = array();
    private $isEnabled = TRUE;

    function __construct(Observable $subject, array $callbacks) {
        $this->subject = $subject;
        $this->assignCallbacks($callbacks);
    }

    private function assignCallbacks(array $callbacks) {
        if (empty($callbacks)) {
            throw new \InvalidArgumentException(
                'No observation callbacks specified'
            );
        }

        foreach ($callbacks as $eventName => $callback) {
            if (is_callable($callback)) {
                $this->callbacks[$eventName] = $callback;
            } else {
                throw new \InvalidArgumentException(
                    'Invalid observation callback'
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
        $this->subject->removeObservation($this);
    }

    function modify(array $callbacks) {
        $this->assignCallbacks($callbacks);
    }

    function replace(array $callbacks) {
        $this->callbacks = array();
        $this->assignCallbacks($callbacks);
    }

    function __invoke($eventName, $data = NULL) {
        if ($this->isEnabled && !empty($this->callbacks[$eventName])) {
            call_user_func($this->callbacks[$eventName], $data);
        }
    }

}
