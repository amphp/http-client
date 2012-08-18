<?php

namespace Artax\Http;

interface ClientConnection extends StreamConnection {
    
    function isInUse();
    function setInUseFlag($inUseFlag);
}
