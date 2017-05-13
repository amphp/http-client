<?php

namespace Amp\Artax;

use Amp\ByteStream\InputStream;
use Amp\Promise;

/**
 * An interface for generating HTTP message bodies + headers.
 */
interface AggregateBody {
    /**
     * Retrieve a key-value array of headers to add to the outbound request.
     *
     * The resolved promise value must be a key-value array mapping header fields to values.
     *
     * @return \Amp\Promise
     */
    public function getHeaders(): Promise;

    /**
     * Retrieve the HTTP message body to be sent.
     *
     * @return InputStream
     */
    public function getBody(): InputStream;

    /**
     * Retrieve the HTTP message body length. If not available, it should return -1.
     *
     * @return Promise
     */
    public function getBodyLength(): Promise;
}
