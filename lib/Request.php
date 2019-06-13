<?php

namespace Amp\Http\Client;

use Amp\Http\Message;
use League\Uri;
use Psr\Http\Message\UriInterface;

/**
 * An HTTP request.
 */
final class Request extends Message
{
    public const DEFAULT_MAX_HEADER_BYTES = 8192;
    public const DEFAULT_MAX_BODY_BYTES = 10485760;

    /** @var string[] */
    private $protocolVersions = ["1.1", "2.0"];

    /** @var string */
    private $method;

    /** @var UriInterface */
    private $uri;

    /** @var RequestBody */
    private $body;

    /** @var int */
    private $tcpConnectTimeout = 10000;

    /** @var int */
    private $tlsHandshakeTimeout = 10000;

    /** @var int */
    private $transferTimeout = 10000;

    /** @var bool */
    private $discardBody = false;

    /** @var int */
    private $maxBodyBytes = self::DEFAULT_MAX_BODY_BYTES;

    /** @var int */
    private $maxHeaderBytes = self::DEFAULT_MAX_HEADER_BYTES;

    public function __construct(string $uri, string $method = "GET")
    {
        $this->uri = Uri\Http::createFromString($uri);
        $this->method = $method;
        $this->body = new StringBody("");
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
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Request
     */
    public function withHeader(string $field, string $value): self
    {
        $clone = clone $this;
        $clone->setHeader($field, $value);

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
        $clone = clone $this;
        $clone->addHeader($field, $value);

        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->setHeaders($headers);

        return $clone;
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
        $clone->removeHeader($field);

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

    /**
     * @return int Timeout in milliseconds for the TCP connection.
     */
    public function getTcpConnectTimeout(): int
    {
        return $this->tcpConnectTimeout;
    }

    public function withTcpConnectTimeout(int $tcpConnectTimeout): self
    {
        $clone = clone $this;
        $clone->tcpConnectTimeout = $tcpConnectTimeout;

        return $clone;
    }

    /**
     * @return int Timeout in milliseconds for the TLS handshake.
     */
    public function getTlsHandshakeTimeout(): int
    {
        return $this->tlsHandshakeTimeout;
    }

    public function withTlsHandshakeTimeout(int $tlsHandshakeTimeout): self
    {
        $clone = clone $this;
        $clone->tlsHandshakeTimeout = $tlsHandshakeTimeout;

        return $clone;
    }

    /**
     * @return int Timeout in milliseconds for the HTTP transfer (not counting TCP connect and TLS handshake)
     */
    public function getTransferTimeout(): int
    {
        return $this->transferTimeout;
    }

    public function withTransferTimeout(int $transferTimeout): self
    {
        $clone = clone $this;
        $clone->transferTimeout = $transferTimeout;

        return $clone;
    }

    public function getHeaderSizeLimit(): int
    {
        return $this->maxHeaderBytes;
    }

    public function withHeaderSizeLimit(int $maxHeaderBytes): self
    {
        $clone = clone $this;
        $clone->maxHeaderBytes = $maxHeaderBytes;

        return $clone;
    }

    public function getBodySizeLimit(): int
    {
        return $this->maxBodyBytes;
    }

    public function withBodySizeLimit(int $maxBodyBytes): self
    {
        $clone = clone $this;
        $clone->maxBodyBytes = $maxBodyBytes;

        return $clone;
    }

    public function isDiscardBody(): bool
    {
        return $this->discardBody;
    }

    public function withBodyDiscarding(): self
    {
        $clone = clone $this;
        $clone->discardBody = true;

        return $clone;
    }

    public function withoutBodyDiscarding(): self
    {
        $clone = clone $this;
        $clone->discardBody = false;

        return $clone;
    }
}
