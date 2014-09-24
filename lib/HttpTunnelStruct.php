<?php

namespace Amp\Artax;

class HttpTunnelStruct {
    public $future;
    public $socket;
    public $writeBuffer;
    public $writeWatcher;
    public $readWatcher;
    public $parser;
}
