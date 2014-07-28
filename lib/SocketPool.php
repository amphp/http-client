<?php

namespace Artax;

interface SocketPool {
    /**
     * I give you a name, you promise me a socket
     *
     * @param string $name
     * @return After\Promise
     */
    public function checkout($name);

    /**
     * Checkin a previously checked-out socket
     *
     * @param resource $socket
     */
    public function checkin($socket);

    /**
     * Clear a previously checked-out socket from the pool
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
