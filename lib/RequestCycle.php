<?php

namespace Artax;

class RequestCycle {
    public $socket;
    public $readWatcher;
    public $writeWatcher;
    public $continueWatcher;
    public $transferTimeoutWatcher;
    public $parser;
    public $request;
    public $response;
    public $requestBody;
    public $writeFuture;
    public $writeBuffer;
    public $isWritingBody;
    public $redirectHistory;
    public $socketProcuredAt;
    public $lastDataRcvdAt;
    public $lastDataSentAt;
    public $bytesRcvd;
    public $bytesSent;
}
