<?php

namespace Amp\Artax;

use Amp\ByteStream\Message;

final class Response {
    /** @var string */
    private $protocolVersion;

    /** @var int */
    private $status;

    /** @var string */
    private $reason;

    /** @var Request */
    private $request;

    /** @var Response|null */
    private $previousResponse;

    /** @var array */
    private $headers;

    /** @var Message */
    private $body;

    public function __construct(string $protocolVersion, int $status, string $reason, array $headers, Message $body, Request $request, Response $previousResponse = null) {
        $this->protocolVersion = $protocolVersion;
        $this->status = $status;
        $this->reason = $reason;
        $this->headers = $headers;
        $this->body = $body;
        $this->request = $request;
        $this->previousResponse = $previousResponse;
    }

    /**
     * Retrieve the requests's HTTP protocol version.
     *
     * @return string
     */
    public function getProtocolVersion(): string {
        return $this->protocolVersion;
    }

    /**
     * Retrieve the response's three-digit HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int {
        return $this->status;
    }

    /**
     * Retrieve the response's (possibly empty) reason phrase.
     *
     * @return string
     */
    public function getReason(): string {
        return $this->reason;
    }

    /**
     * Retrieve the Request instance that resulted in this Response instance.
     *
     * @return Request
     */
    public function getRequest(): Request {
        return $this->request;
    }

    /**
     * Retrieve the original Request instance associated with this Response instance.
     *
     * A given Response may be the result of one or more redirects. This method is a shortcut to
     * access information from the original Request that led to this response.
     *
     * @return Request
     */
    public function getOriginalRequest(): Request {
        if (empty($this->previousResponse)) {
            return $this->request;
        }

        return $this->previousResponse->getOriginalRequest();
    }

    /**
     * If this Response is the result of a redirect traverse up the redirect history.
     *
     * @return Response|null
     */
    public function getPreviousResponse() {
        return $this->previousResponse;
    }

    /**
     * Does the message contain the specified header field (case-insensitive)?
     *
     * @param string $field Header name.
     *
     * @return bool
     */
    public function hasHeader(string $field): bool {
        return isset($this->headers[\strtolower($field)]);
    }

    /**
     * Retrieve the first occurrence of the specified header in the message.
     *
     * If multiple headers exist for the specified field only the value of the first header is returned. Applications
     * may use `getHeaderArray()` to retrieve a list of all header values received for a given field.
     *
     * A `null` return indicates the requested header field was not present.
     *
     * @param string $field Header name.
     *
     * @return string|null Header value or `null` if no header with name `$field` exists.
     */
    public function getHeader(string $field) {
        return $this->headers[\strtolower($field)][0] ?? null;
    }

    /**
     * Retrieve all occurrences of the specified header in the message.
     *
     * Applications may use `getHeader()` to access only the first occurrence.
     *
     * @param string $field Header name.
     *
     * @return array Header values.
     */
    public function getHeaderArray(string $field): array {
        return $this->headers[\strtolower($field)] ?? [];
    }

    /**
     * Retrieve an associative array of headers matching field names to an array of field values.
     *
     * @return array
     */
    public function getAllHeaders(): array {
        return $this->headers;
    }

    public function getBody(): Message {
        return $this->body;
    }
}
