<?php

namespace Artax;

class RequestState {
    
    public $response;
    public $request;
    public $authority;
    public $socket;
    public $socketObservation;
    public $onResponse;
    public $onError;
    public $parser;
    public $redirectHistory = [];
    public $transferTimeoutSubscription;
    public $bodyDrainObservation;
    public $continueDelaySubscription;
    
}
