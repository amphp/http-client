<?php

namespace Artax;

final class SocketStruct {
    const INIT = 0;
    const CONNECTING = 1;
    const ENCRYPTING = 2;
    const CHECKED_OUT = 3;
    const CHECKED_IN = 4;
    const ERROR = 5;

    public $state = self::INIT;
    public $id;
    public $name;
    public $resource;
    public $deferredSocket;
    public $ioWriteWatcher;
    public $connectTimeoutWatcher;
    public $idleTimeoutWatcher;
}
