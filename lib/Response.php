<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\Payload;
use Amp\Promise;
use Amp\Success;

/**
 * An HTTP response.
 *
 * This interface allows mocking responses and allows custom implementations.
 *
 * `DefaultClient` uses an anonymous class to implement this interface.
 */
final class Response
{
    private $protocolVersion;
    private $status;
    private $reason;
    private $request;
    private $headers;
    private $body;
    private $connectionInfo;
    private $completionPromise;
    private $previousResponse;

    public function __construct(
        string $protocolVersion,
        int $status,
        string $reason,
        array $headers,
        InputStream $body,
        Request $request,
        ConnectionInfo $connectionInfo,
        ?Promise $completionPromise = null,
        ?Response $previousResponse = null
    ) {
        $this->protocolVersion = $protocolVersion;
        $this->status = $status;
        $this->reason = $reason;
        $this->body = new Payload($body);
        $this->request = $request;
        $this->connectionInfo = $connectionInfo;
        $this->completionPromise = $completionPromise ?? new Success;
        $this->previousResponse = $previousResponse;

        $this->headers = [];
        foreach ($headers as $field => $values) {
            $key = \strtolower($field);
            foreach ($values as $value) {
                $this->headers[$key][] = $value;
            }
        }
    }

    /**
     * Retrieve the requests's HTTP protocol version.
     *
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $protocolVersion): self
    {
        $clone = clone $this;
        $clone->protocolVersion = $protocolVersion;

        return $clone;
    }

    /**
     * Retrieve the response's three-digit HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    public function withStatus(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    /**
     * Retrieve the response's (possibly empty) reason phrase.
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    public function withReason(string $reason): self
    {
        $clone = clone $this;
        $clone->reason = $reason;

        return $clone;
    }

    /**
     * Retrieve the Request instance that resulted in this Response instance.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    public function withRequest(Request $request): self
    {
        $clone = clone $this;
        $clone->request = $request;

        return $clone;
    }

    /**
     * Retrieve the original Request instance associated with this Response instance.
     *
     * A given Response may be the result of one or more redirects. This method is a shortcut to
     * access information from the original Request that led to this response.
     *
     * @return Request
     */
    public function getOriginalRequest(): Request
    {
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
    public function getPreviousResponse(): ?Response
    {
        return $this->previousResponse;
    }

    /**
     * Does the message contain the specified header field (case-insensitive)?
     *
     * @param string $field Header name.
     *
     * @return bool
     */
    public function hasHeader(string $field): bool
    {
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
    public function getHeader(string $field): ?string
    {
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
    public function getHeaderArray(string $field): array
    {
        return $this->headers[\strtolower($field)] ?? [];
    }

    /**
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Response
     */
    public function withHeader(string $field, string $value): self
    {
        $field = \trim($field);
        $lower = \strtolower($field);

        $clone = clone $this;

        $clone->headers[$lower] = [\trim($value)];

        return $clone;
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Response
     */
    public function withAddedHeader(string $field, string $value): self
    {
        $field = \trim($field);
        $lower = \strtolower($field);

        $clone = clone $this;

        $headers = $clone->headers[$lower] ?? [];
        $headers[] = \trim($value);

        $clone->headers[$lower] = $headers;

        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;

        foreach ($headers as $field => $values) {
            if (!\is_string($field) && !\is_int($field)) {
                // PHP converts integer strings automatically to integers.
                // Later versions of PHP might allow other key types.
                // @codeCoverageIgnoreStart
                /** @noinspection PhpUndefinedClassInspection */
                throw new \TypeError("All array keys for withHeaders must be strings");
                // @codeCoverageIgnoreEnd
            }

            $field = \trim($field);
            $lower = \strtolower($field);

            if (!\is_array($values)) {
                $values = [$values];
            }

            $clone->headers[$lower] = [];

            foreach ($values as $value) {
                if (!\is_string($value)
                    && !\is_int($value)
                    && !\is_float($value)
                    && !(\is_object($value) && \method_exists($value, '__toString'))
                ) {
                    /** @noinspection PhpUndefinedClassInspection */
                    throw new \TypeError("All values for withHeaders must be string or an array of strings");
                }

                $clone->headers[$lower][] = \trim($value);
            }

            if (empty($clone->headers[$lower])) {
                unset($clone->headers[$lower]);
            }
        }

        return $clone;
    }

    /**
     * Retrieve an associative array of headers matching field names to an array of field values.
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $field Header name.
     *
     * @return Response
     */
    public function withoutHeader(string $field): self
    {
        $clone = clone $this;
        unset($clone->headers[\strtolower($field)]);

        return $clone;
    }

    /**
     * Retrieve the response body.
     *
     * Note: If you stream a Message, you can't consume the payload twice.
     *
     * @return Payload
     */
    public function getBody(): Payload
    {
        return $this->body;
    }

    public function getConnectionInfo(): ConnectionInfo
    {
        return $this->connectionInfo;
    }

    public function getCompletionPromise(): Promise
    {
        return $this->completionPromise;
    }

    public function awaitCompletion(): Promise
    {
        return $this->completionPromise;
    }

    public function withConnectionInfo(ConnectionInfo $connectionInfo): self
    {
        $clone = clone $this;
        $clone->connectionInfo = $connectionInfo;

        return $clone;
    }

    public function withPreviousResponse(?Response $previousResponse): self
    {
        $clone = clone $this;
        $clone->previousResponse = $previousResponse;

        return $clone;
    }

    public function withBody(InputStream $body): self
    {
        $clone = clone $this;
        $clone->body = new Payload($body);

        return $clone;
    }
}
