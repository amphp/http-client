<?php

namespace Artax;

class RequestState {
    
    public $response;
    public $request;
    public $authority;
    public $socket;
    public $sockSub;
    public $onResponse;
    public $onError;
    public $parser;
    public $redirectHistory = [];
    public $transferTimeoutSubscription;
    public $bodyDrainSubscription;
    public $continueDelaySubscription;
    
}
