<?php

namespace Amp\Artax;

class HttpTunnelStruct {
    use \Amp\Struct;
    
    /** @var \Amp\Deferred */
    public $deferred;
    
    public $socket;
    public $writeBuffer;
    public $writeWatcher;
    public $readWatcher;
    
    /** @var \Amp\Artax\Parser */
    public $parser;
}
