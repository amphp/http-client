<?php

namespace Amp\Http\Client;

use Amp\ByteStream\ReadableStream;

/**
 * An interface for generating HTTP message bodies + headers.
 */
interface RequestBody
{
    /**
     * Retrieve a key-value array of headers to add to the outbound request.
     *
     * @return array Must be a key-value array mapping header fields to header values.
     */
    public function getHeaders(): array;

    /**
     * Create the HTTP message body to be sent.
     *
     * Further calls MUST return a new stream to make it possible to resend bodies on redirects.
     */
    public function createBodyStream(): ReadableStream;

    /**
     * Retrieve the HTTP message body length. If not available, return null.
     */
    public function getBodyLength(): ?int;
}
