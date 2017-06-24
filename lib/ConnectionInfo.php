<?php

namespace Amp\Artax;

final class ConnectionInfo {
    private $localAddress;
    private $remoteAddress;
    private $tlsInfo;

    public function __construct(string $localAddress, string $remoteAddress, TlsInfo $tlsInfo = null) {
        $this->localAddress = $localAddress;
        $this->remoteAddress = $remoteAddress;
        $this->tlsInfo = $tlsInfo;
    }

    /**
     * @return string
     */
    public function getLocalAddress(): string {
        return $this->localAddress;
    }

    /**
     * @return string
     */
    public function getRemoteAddress(): string {
        return $this->remoteAddress;
    }

    /**
     * @return TlsInfo|null
     */
    public function getTlsInfo() {
        return $this->tlsInfo;
    }
}
