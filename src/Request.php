<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Body\StringBody;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Message;
use League\Uri;
use Psr\Http\Message\UriInterface;

/**
 * An HTTP request.
 */
final class Request extends Message
{
    use ForbidSerialization;

    public const DEFAULT_HEADER_SIZE_LIMIT = 2 * 8192;
    public const DEFAULT_BODY_SIZE_LIMIT = 10485760;

    private static function clone($value)
    {
        if ($value === null || \is_scalar($value)) {
            return $value;
        }

        // force deep cloning
        return \unserialize(\serialize($value), ['allowed_classes' => true]);
    }

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

    /** @var mixed[] */
    private $attributes = [];

    /**
     * Request constructor.
     *
     * @param string|UriInterface $uri
     * @param string              $method
     */
    public function __construct($uri, string $method = "GET")
    {
        $this->setUri($uri);
        $this->setMethod($method);
        $this->setBody('');
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

    /**
     * Note: This method returns a deep clone of the request's attributes, so you can't modify the request attributes
     * by modifying the returned value in any way.
     *
     * @return mixed[] An array of all request attributes in the request's local storage, indexed by name.
     */
    public function getAttributes(): array
    {
        return self::clone($this->attributes);
    }

    /**
     * Check whether a variable with the given name exists in the request's local storage.
     *
     * Each request has its own local storage to which applications and interceptors may read and write data.
     * Other interceptors which are aware of this data can then access it without the server being tightly coupled to
     * specific implementations.
     *
     * @param string $name Name of the attribute, should be namespaced with a vendor and package namespace like classes.
     *
     * @return bool
     */
    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    /**
     * Retrieve a variable from the request's local storage.
     *
     * Each request has its own local storage to which applications and interceptors may read and write data.
     * Other interceptors which are aware of this data can then access it without the server being tightly coupled to
     * specific implementations.
     *
     * Note: This method returns a deep clone of the request's attribute, so you can't modify the request attribute
     * by modifying the returned value in any way.
     *
     * @param string $name Name of the attribute, should be namespaced with a vendor and package namespace like classes.
     *
     * @return mixed
     *
     * @throws MissingAttributeError If an attribute with the given name does not exist.
     */
    public function getAttribute(string $name)
    {
        if (!$this->hasAttribute($name)) {
            throw new MissingAttributeError("The requested attribute '{$name}' does not exist");
        }

        return self::clone($this->attributes[$name]);
    }

    /**
     * Assign a variable to the request's local storage.
     *
     * Each request has its own local storage to which applications and interceptors may read and write data.
     * Other interceptors which are aware of this data can then access it without the server being tightly coupled to
     * specific implementations.
     *
     * Note: This method performs a deep clone of the value via serialization, so you can't modify the given value
     * after setting it.
     *
     * **Example**
     *
     * ```php
     * $request->setAttribute(Timing::class, $stopWatch);
     * ```
     *
     * @param string $name Name of the attribute, should be namespaced with a vendor and package namespace like classes.
     * @param mixed  $value Value of the attribute, might be any serializable value.
     */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = self::clone($value);
    }

    /**
     * Remove an attribute from the request's local storage.
     *
     * @param string $name Name of the attribute, should be namespaced with a vendor and package namespace like classes.
     *
     * @throws MissingAttributeError If an attribute with the given name does not exist.
     */
    public function removeAttribute(string $name): void
    {
        if (!$this->hasAttribute($name)) {
            throw new MissingAttributeError("The requested attribute '{$name}' does not exist");
        }

        unset($this->attributes[$name]);
    }

    /**
     * Remove all attributes from the request's local storage.
     */
    public function removeAttributes(): void
    {
        $this->attributes = [];
    }

    public function isIdempotent(): bool
    {
        // https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
        return \in_array($this->method, ['GET', 'HEAD', 'PUT', 'DELETE'], true);
    }

    private function createUriFromString(string $uri): UriInterface
    {
        return Uri\Http::createFromString($uri);
    }
}
