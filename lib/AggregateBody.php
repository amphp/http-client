<?php

namespace Amp\Artax;

/**
 * An interface for generating customized HTTP message bodies + headers.
 */
interface AggregateBody {

    /**
     * Retrieve the HTTP message body to be sent
     *
     * The resolved awaitable value may be a string or an Iterator. An event reactor is always passed
     * to assist with asynchronous value resolution.
     *
     * @return \Interop\Async\Awaitable
     */
    public function getBody();

    /**
     * Retrieve a key-value array of headers to add to the outbound request
     *
     * The resolved awaitable value must be a key-value array mapping header fields to values. An
     * event reactor is always passed to assist with asynchronous value resolution.
     *
     * @return \Interop\Async\Awaitable
     */
    public function getHeaders();

    /**
     * Retrieve the entity body's content length
     *
     * The resolved value must either be an integer length or null if the entity body's content
     * length is not known.
     *
     * @return \Interop\Async\Awaitable
     */
    public function getLength();
}
