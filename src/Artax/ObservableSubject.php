<?php

namespace Artax;

trait ObservableSubject {

    private $observations;

    function addObservation(array $eventListenerMap) {
        $this->observations = $this->observations ?: new \SplObjectStorage;
        $observation = new Observation($this, $eventListenerMap);
        $this->observations->attach($observation);

        return $observation;
    }

    function removeObservation(Observation $observation) {
        if ($this->observations) {
            $this->observations->detach($observation);
        }
    }

    function removeAllObservations() {
        $this->observations = new \SplObjectStorage;
    }

    protected function notifyObservations($event, $data = NULL) {
        $this->observations = $this->observations ?: new \SplObjectStorage;

        foreach ($this->observations as $observation) {
            call_user_func($observation, $event, $data);
        }
    }

}
