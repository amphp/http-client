<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Body\StringBody;
use Amp\Http\Message;
use League\Uri;
use Psr\Http\Message\UriInterface;

/**
 * An HTTP request.
 */
final class Request extends Message
{
    public const DEFAULT_HEADER_SIZE_LIMIT = 2 * 8192;
    public const DEFAULT_BODY_SIZE_LIMIT = 10485760;

    /** @var string[] */
    private $protocolVersions = ['1.1', '2'];

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

    /** @var int */
    private $bodySizeLimit = self::DEFAULT_BODY_SIZE_LIMIT;

    /** @var int */
    private $headerSizeLimit = self::DEFAULT_HEADER_SIZE_LIMIT;

    /** @var callable|null */
    private $onPush;

    /**
     * Request constructor.
     *
     * @param string|UriInterface $uri
     * @param string              $method
     */
    public function __construct($uri, string $method = "GET")
    {
        $this->uri = $uri instanceof UriInterface ? $uri : $this->createUriFromString($uri);
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
     */
    public function setProtocolVersions(array $versions): void
    {
        $versions = \array_unique($versions);

        if (empty($versions)) {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \Error("Empty array of protocol versions provided, must not be empty.");
        }

        foreach ($versions as $version) {
            if (!\in_array($version, ["1.0", "1.1", "2"], true)) {
                /** @noinspection PhpUndefinedClassInspection */
                throw new \Error(
                    "Invalid HTTP protocol version: " . $version
                );
            }
        }

        $this->protocolVersions = $versions;
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
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
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
     * @param string|UriInterface $uri
     */
    public function setUri($uri): void
    {
        $this->uri = $uri instanceof UriInterface ? $uri : $this->createUriFromString($uri);
    }

    /**
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param string          $field Header name.
     * @param string|string[] $value Header value.
     */
    public function setHeader(string $field, $value): void
    {
        if (($field[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::setHeader($field, $value);
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param string          $field Header name.
     * @param string|string[] $value Header value.
     */
    public function addHeader(string $field, $value): void
    {
        if (($field[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::addHeader($field, $value);
    }

    public function setHeaders(array $headers): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::setHeaders($headers);
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $field Header name.
     */
    public function removeHeader(string $field): void
    {
        parent::removeHeader($field);
    }

    /**
     * Retrieve the message entity body.
     */
    public function getBody(): RequestBody
    {
        return $this->body;
    }

    /**
     * Assign the message entity body.
     *
     * @param mixed $body
     */
    public function setBody($body): void
    {
        if ($body === null) {
            $this->body = new StringBody("");
        } elseif (\is_string($body)) {
            $this->body = new StringBody($body);
        } elseif (\is_scalar($body)) {
            $this->body = new StringBody(\var_export($body, true));
        } elseif ($body instanceof RequestBody) {
            $this->body = $body;
        } else {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \TypeError("Invalid body type: " . \gettype($body));
        }
    }

    /**
     * Attaches a callback to the request that is invoked when the server pushes an additional resource.
     * The callback is given three parameters: the Request generated from the pushed resource, a promise for the
     * Response containing the pushed resource, and an instance of Amp\CancellationTokenSource that may be used to
     * refuse (cancel) the pushed resource.
     *
     * Example:
     * function (Request $request, Promise $promise, CancellationTokenSource $tokenSource): \Generator {
     *     $uri = $request->getUri(); // URI of pushed resource.
     *     $response = yield $promise; // Wait for resource to arrive.
     *     // Use Response object from resolved promise.
     * }
     *
     * @param callable|null $onPush
     */
    public function onPush(?callable $onPush = null): void
    {
        $this->onPush = $onPush;
    }

    /**
     * @return callable|null
     */
    public function getPushCallable(): ?callable
    {
        return $this->onPush;
    }

    /**
     * @return int Timeout in milliseconds for the TCP connection.
     */
    public function getTcpConnectTimeout(): int
    {
        return $this->tcpConnectTimeout;
    }

    public function setTcpConnectTimeout(int $tcpConnectTimeout): void
    {
        $this->tcpConnectTimeout = $tcpConnectTimeout;
    }

    /**
     * @return int Timeout in milliseconds for the TLS handshake.
     */
    public function getTlsHandshakeTimeout(): int
    {
        return $this->tlsHandshakeTimeout;
    }

    public function setTlsHandshakeTimeout(int $tlsHandshakeTimeout): void
    {
        $this->tlsHandshakeTimeout = $tlsHandshakeTimeout;
    }

    /**
     * @return int Timeout in milliseconds for the HTTP transfer (not counting TCP connect and TLS handshake)
     */
    public function getTransferTimeout(): int
    {
        return $this->transferTimeout;
    }

    public function setTransferTimeout(int $transferTimeout): void
    {
        $this->transferTimeout = $transferTimeout;
    }

    public function getHeaderSizeLimit(): int
    {
        return $this->headerSizeLimit;
    }

    public function setHeaderSizeLimit(int $headerSizeLimit): void
    {
        $this->headerSizeLimit = $headerSizeLimit;
    }

    public function getBodySizeLimit(): int
    {
        return $this->bodySizeLimit;
    }

    public function setBodySizeLimit(int $bodySizeLimit): void
    {
        $this->bodySizeLimit = $bodySizeLimit;
    }

    public function isIdempotent(): bool
    {
        // https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
        return \in_array($this->method, ['GET', 'HEAD', 'PUT', 'DELETE'], true);
    }

    public function __sleep(): array
    {
        throw new \Error('Serialization of class ' . __CLASS__ . ' is not allowed');
    }

    private function createUriFromString(string $uri): UriInterface
    {
        return Uri\Http::createFromString($uri);
    }
}
