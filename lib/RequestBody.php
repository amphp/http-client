<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\Promise;

/**
 * An interface for generating HTTP message bodies + headers.
 */
interface RequestBody
{
    /**
     * Retrieve a key-value array of headers to add to the outbound request.
     *
     * The resolved promise value must be a key-value array mapping header fields to values.
     *
     * @return Promise
     */
    public function getHeaders(): Promise;

    /**
     * Create the HTTP message body to be sent.
     *
     * Further calls MUST return a new stream to make it possible to resend bodies on redirects.
     *
     * @return InputStream
     */
    public function createBodyStream(): InputStream;

    /**
     * Retrieve the HTTP message body length. If not available, return -1.
     *
     * @return Promise
     */
    public function getBodyLength(): Promise;
}
