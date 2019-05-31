<?php

namespace Amp\Http\Client;

use League\Uri;
use Psr\Http\Message\UriInterface;

/**
 * An HTTP request.
 */
final class Request
{
    /** @var string[] */
    private $protocolVersions = ["1.1", "2.0"];

    /** @var string */
    private $method;

    /** @var UriInterface */
    private $uri;

    /** @var array headers with lowercase keys */
    private $headers = [];

    /** @var RequestBody */
    private $body;

    /** @var RequestOptions */
    private $options;

    public function __construct(string $uri, string $method = "GET")
    {
        $this->uri = Uri\Http::createFromString($uri);
        $this->method = $method;
        $this->body = new StringBody("");
        $this->options = new RequestOptions;
    }

    /**
     * Retrieve the requests's acceptable HTTP protocol versions.
     *
     * @return string[]
     */
    public function getProtocolVersions(): array
    {
        return $this->protocolVersions;
    }

    /**
     * Assign the requests's acceptable HTTP protocol versions.
     *
     * The HTTP client might choose any of these.
     *
     * @param string[] $versions
     *
     * @return Request
     */
    public function withProtocolVersions(array $versions): self
    {
        $versions = \array_unique($versions);

        if (empty($versions)) {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \Error("Empty array of protocol versions provided, must not be empty.");
        }

        foreach ($versions as $version) {
            if (!\in_array($version, ["1.0", "1.1", "2.0"], true)) {
                /** @noinspection PhpUndefinedClassInspection */
                throw new \Error(
                    "Invalid HTTP protocol version: " . $version
                );
            }
        }

        if ($this->protocolVersions === $versions) {
            return $this;
        }

        $clone = clone $this;
        $clone->protocolVersions = $versions;

        return $clone;
    }

    /**
     * Retrieve the request's HTTP method verb.
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Specify the request's HTTP method verb.
     *
     * @param string $method
     *
     * @return Request
     */
    public function withMethod(string $method): self
    {
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
     * @return UriInterface
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Specify the request's HTTP URI.
     *
     * @param UriInterface $uri
     *
     * @return Request
     */
    public function withUri(UriInterface $uri): self
    {
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
     * @return Request
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
     * @return Request
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
     * @return Request
     */
    public function withoutHeader(string $field): self
    {
        $clone = clone $this;
        unset($clone->headers[\strtolower($field)]);

        return $clone;
    }

    /**
     * Retrieve the message entity body.
     *
     * @return mixed
     */
    public function getBody(): RequestBody
    {
        return $this->body;
    }

    /**
     * Assign the message entity body.
     *
     * @param mixed $body
     *
     * @return Request
     */
    public function withBody($body): self
    {
        $clone = clone $this;

        if ($body === null) {
            $clone->body = new StringBody("");
        } elseif (\is_scalar($body)) {
            $clone->body = new StringBody((string) $body);
        } elseif ($body instanceof RequestBody) {
            $clone->body = $body;
        } else {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \TypeError("Invalid body type: " . \gettype($body));
        }

        return $clone;
    }

    public function getOptions(): RequestOptions
    {
        return $this->options;
    }

    public function withOptions(RequestOptions $options): self
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }
}
