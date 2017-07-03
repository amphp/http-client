<?php

namespace Amp\Artax;

/**
 * Contains generic meta information about a response, currently only ConnectionInfo, but might be extended later.
 */
final class MetaInfo {
    private $connectionInfo;

    public function __construct(ConnectionInfo $connectionInfo) {
        $this->connectionInfo = $connectionInfo;
    }

    public function getConnectionInfo(): ConnectionInfo {
        return $this->connectionInfo;
    }
}
