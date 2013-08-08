<?php

namespace Artax;

use Alert\Reactor;

class SocketFactory {
    
    function make(Reactor $reactor, $authority) {
        return new Socket($reactor, $authority);
    }
    
}

