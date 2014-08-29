<?php

namespace Artax;

class RequestCycle {
    /**
     * @var \After\Future
     */
    public $futureResponse;
    public $options;
    public $socket;
    public $readWatcher;
    public $continueWatcher;
    public $transferTimeoutWatcher;
    public $uri;
    public $request;
    public $response;
    public $redirectHistory;
    public $parser;
    public $socketProcuredAt;
    public $lastDataRcvdAt;
    public $lastDataSentAt;
    public $bytesRcvd;
    public $bytesSent;
}
