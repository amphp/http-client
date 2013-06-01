<?php

namespace Artax;

use Amp\Reactor;

class SocketFactory {
    
    function make(Reactor $reactor, $authority) {
        return new Socket($reactor, $authority);
    }
    
}

