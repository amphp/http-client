<?php

namespace Amp\Artax;

/**
 * An HTTP request.
 */
final class Request {
    /** @var string */
    private $protocolVersion = "1.1";

    /** @var string */
    private $method;

    /** @var string */
    private $uri;

    /** @var array headers with lowercase keys */
    private $headers = [];

    /** @var array lowercase header to actual case map */
    private $headerCaseMap = [];

    /** @var AggregateBody */
    private $body;

    public function __construct(string $uri, string $method = "GET") {
        $this->uri = $uri;
        $this->method = $method;
        $this->body = new StringBody("");
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
     * Assign the requests's HTTP protocol version.
     *
     * @param string $version
     *
     * @return Request
     */
    public function withProtocolVersion(string $version): self {
        if ($version !== "1.0" && $version !== "1.1") {
            throw new HttpException(
                "Invalid HTTP protocol version: " . $version
            );
        }

        if ($this->protocolVersion === $version) {
            return $this;
        }

        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    /**
     * Retrieve the request's HTTP method verb.
     *
     * @return string
     */
    public function getMethod(): string {
        return $this->method;
    }

    /**
     * Specify the request's HTTP method verb.
     *
     * @param string $method
     *
     * @return Request
     */
    public function withMethod(string $method): self {
        if ($this->method === $method) {
            return $this;
        }

        $clone = clone $this;
        $clone->method = $method;

        return $clone;
    }

    /**
     * Retrieve the request's URI.
     *
     * @return string
     */
    public function getUri(): string {
        return $this->uri;
    }

    /**
     * Specify the request's HTTP URI.
     *
     * @param string
     *
     * @return Request
     */
    public function withUri(string $uri): self {
        $clone = clone $this;
        $clone->uri = $uri;

        return $clone;
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
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Request
     */
    public function withHeader(string $field, string $value): self {
        $field = \trim($field);
        $lower = \strtolower($field);

        $clone = clone $this;

        $clone->headers[$lower] = [\trim($value)];
        $clone->headerCaseMap[$lower] = $field;

        return $clone;
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Request
     */
    public function withAddedHeader(string $field, string $value): self {
        $field = \trim($field);
        $lower = \strtolower($field);

        $clone = clone $this;

        $headers = $clone->headers[$lower] ?? [];
        $headers[] = \trim($value);

        $clone->headers[$lower] = $headers;
        $clone->headerCaseMap[$lower] = $field;

        return $clone;
    }

    public function withAllHeaders(array $headers): self {
        $clone = clone $this;

        foreach ($headers as $field => $values) {
            if (!\is_string($field)) {
                throw new \TypeError("All array keys for withAllHeaders must be strings");
            }

            $field = \trim($field);
            $lower = \strtolower($field);

            if (!\is_array($values)) {
                $values = [$values];
            }

            $clone->headers[$lower] = [];

            foreach ($values as $value) {
                if (!\is_string($field)) {
                    throw new \TypeError("All values for withAllHeaders must be string or an array of strings");
                }

                $clone->headers[$lower][] = \trim($value);
            }

            $clone->headerCaseMap[$lower] = $field;
        }

        return $clone;
    }

    /**
     * Retrieve an associative array of headers matching field names to an array of field values.
     *
     * @return array
     */
    public function getAllHeaders(): array {
        return $this->headers;
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $field Header name.
     *
     * @return Request
     */
    public function withoutHeader(string $field): self {
        $lower = \strtolower($field);

        $clone = clone $this;

        unset(
            $clone->headerCaseMap[$lower],
            $clone->headers[$lower]
        );

        return $clone;
    }

    /**
     * Retrieve the message entity body.
     *
     * @return mixed
     */
    public function getBody(): AggregateBody {
        return $this->body;
    }

    /**
     * Assign the message entity body.
     *
     * @param mixed $body
     *
     * @return Request
     */
    public function withBody($body): self {
        $clone = clone $this;

        if ($body === null) {
            $clone->body = new StringBody("");
        } elseif (\is_scalar($body)) {
            $clone->body = new StringBody((string) $body);
        } elseif ($body instanceof AggregateBody) {
            $clone->body = $body;
        } else {
            throw new \TypeError("Invalid body type: " . gettype($body));
        }

        return $clone;
    }
}
