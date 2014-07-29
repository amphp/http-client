<?php

namespace Artax;

class TcpConnectorStruct {
    public $uri;
    public $scheme;
    public $host;
    public $port;
    public $resolvedAddress;
    public $socket;
    public $connectWatcher;
    public $timeoutWatcher;
    public $deferred;
    public $options;
}
