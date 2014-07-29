<?php

namespace Artax;

interface SocketPool {
    /**
     * I give you a URI, you promise me a socket at some point in the future
     *
     * @param string $uri
     * @return After\Promise
     */
    public function checkout($uri);

    /**
     * Checkin a previously checked-out socket
     *
     * @param resource $socket
     */
    public function checkin($socket);

    /**
     * Clear a previously checked-out socket from the pool
     * 
     * @param resource $socket
     */
    public function clear($socket);

    /**
     * Set a socket pool option
     *
     * @param int|string $option
     * @param mixed $value
     */
    public function setOption($option, $value);
}
