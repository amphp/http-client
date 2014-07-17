<?php

namespace Artax;

/**
 * An interface allowing custom HTTP message bodies
 */
interface AggregateBody {
    /**
     * Returns the raw HTTP message body
     *
     * @return mixed Must return a scalar value or Iterator instance
     */
    public function getBody();

    /**
     * Return a key-value array of headers to add to the outbound request
     *
     * @return array
     */
    public function getHeaders();
}
