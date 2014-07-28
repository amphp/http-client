<?php

namespace Artax;

class RequestCycle {
    public $socket;
    public $readWatcher;
    public $writeWatcher;
    public $continueWatcher;
    public $transferTimeoutWatcher;
    public $uri;
    public $request;
    public $response;
    public $requestBody;
    public $writeBuffer;
    public $isWritingBody;
    public $redirectHistory;
    public $parser;
    public $socketProcuredAt;
    public $lastDataRcvdAt;
    public $lastDataSentAt;
    public $bytesRcvd;
    public $bytesSent;
}
