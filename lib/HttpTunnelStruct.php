<?php

namespace Amp\Artax;

class HttpTunnelStruct {
    public $promisor;
    public $socket;
    public $writeBuffer;
    public $writeWatcher;
    public $readWatcher;
    public $parser;
}
