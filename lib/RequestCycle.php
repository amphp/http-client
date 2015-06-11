<?php

namespace Amp\Artax;

class RequestCycle {
    /**
     * @var \Amp\Deferred
     */
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
