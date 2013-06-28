<?php

namespace Artax\Ext\Progress;

class ProgressState {
    
    public $socketReadyAt;
    public $redirectCount = 0;
    public $bytesRcvd = 0;
    public $headerBytes;
    public $contentLength;
    public $percentComplete;
    public $bytesPerSecond;
    public $isComplete = FALSE;
    public $progressBar;

}

