<?php

namespace Amp\Artax;

class RequestCycle {
    use \Amp\Struct;
    
    /** @var \Amp\Emitter */
    public $futureResponse;
    public $options;
    public $socket;
    public $socketCheckoutUri;
    public $redirectedSockets;
    public $readWatcher;
    public $continueWatcher;
    public $transferTimeoutWatcher;
    public $uri;
    public $request;
    public $response;
    public $previousResponse;
    public $redirectHistory;
    public $parser;
    public $socketProcuredAt;
    public $lastDataRcvdAt;
    public $lastDataSentAt;
    public $bytesRcvd;
    public $bytesSent;
    public $retryCount;
}
