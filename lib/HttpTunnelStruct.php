<?php

namespace Amp\Artax;

class HttpTunnelStruct {
    public $deferred;
    public $socket;
    public $writeBuffer;
    public $writeWatcher;
    public $readWatcher;
    public $parser;
}
