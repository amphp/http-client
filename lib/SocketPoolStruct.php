<?php

namespace Amp\Artax;

class SocketPoolStruct {
    public $id;
    public $uri;
    public $resource;
    public $isAvailable;
    public $idleWatcher;
    public $msIdleTimeout;
}
