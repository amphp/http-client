<?php

namespace Amp\Artax;

class SocketPoolStruct {
    use \Amp\Struct;
    
    public $id;
    public $uri;
    public $resource;
    public $isAvailable;
    public $idleWatcher;
    public $msIdleTimeout;
}
