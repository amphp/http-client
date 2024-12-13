<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Http\Client\Connection\UpgradedSocket;
use Amp\Http\Client\Internal\EventInvoker;
use Amp\Http\Client\Internal\Phase;
use Amp\Http\HttpMessage;
use Amp\Http\HttpRequest;
use League\Uri;
use Psr\Http\Message\UriInterface;
use function Amp\async;

/**
 * An HTTP request.
 *
 * @psalm-import-type HeaderParamValueType from HttpMessage
 * @psalm-import-type HeaderParamArrayType from HttpMessage
 * @psalm-type ProtocolVersion = '1.0'|'1.1'|'2'
 */
final class Request extends HttpRequest
{
    use ForbidSerialization;

    public const DEFAULT_HEADER_SIZE_LIMIT = 2 * 8192;
    public const DEFAULT_BODY_SIZE_LIMIT = 10485760;

    /** @var list<ProtocolVersion> */
    private array $protocolVersions = ['1.1', '2'];

    private HttpContent $body;

    private float $tcpConnectTimeout = 10;

    private float $tlsHandshakeTimeout = 10;

    private float $transferTimeout = 10;

    private float $inactivityTimeout = 10;

    /** @var non-negative-int */
    private int $bodySizeLimit = self::DEFAULT_BODY_SIZE_LIMIT;

    /** @var non-negative-int */
    private int $headerSizeLimit = self::DEFAULT_HEADER_SIZE_LIMIT;

    /** @var null|\Closure(Request, Future): void */
    private ?\Closure $onPush = null;

    /** @var null|\Closure(UpgradedSocket, Request, Response): void */
    private ?\Closure $onUpgrade = null;

    /** @var null|\Closure(Response): void */
    private ?\Closure $onInformationalResponse = null;

    /** @var array<non-empty-string, mixed> */
    private array $attributes = [];

    /** @var EventListener[] */
    private array $eventListeners = [];

    /**
     * @param non-empty-string $method
     */
    public function __construct(UriInterface|string $uri, string $method = "GET", HttpContent|string $body = '')
    {
        parent::__construct($method, $uri instanceof UriInterface ? $uri : $this->createUriFromString($uri));

        $this->setBody($body);
    }

    public function addEventListener(EventListener $eventListener): void
    {
        $this->eventListeners[\spl_object_id($eventListener)] = $eventListener;
    }

    /**
     * @return list<EventListener>
     */
    public function getEventListeners(): array
    {
        return \array_values($this->eventListeners);
    }

    /**
     * @return bool Whether processing the request might have already been started on the server.
     */
    public function isUnprocessed(): bool
    {
        if (EventInvoker::isRejected($this)) {
            return true;
        }

        return \in_array(EventInvoker::getPhase($this), [Phase::Unprocessed, Phase::Blocked, Phase::Connected], true);
    }

    /**
     * Retrieve the request's acceptable HTTP protocol versions.
     *
     * @return list<ProtocolVersion>
     */
    public function getProtocolVersions(): array
    {
        return $this->protocolVersions;
    }

    /**
     * Assign the request's acceptable HTTP protocol versions.
     *
     * The HTTP client might choose any of these.
     *
     * @param array<ProtocolVersion> $versions
     */
    public function setProtocolVersions(array $versions): void
    {
        $versions = \array_unique($versions);

        if (empty($versions)) {
            throw new \Error("Empty array of protocol versions provided, must not be empty.");
        }

        foreach ($versions as $version) {
            if (!\in_array($version, ["1.0", "1.1", "2"], true)) {
                throw new \Error(
                    "Invalid HTTP protocol version: " . $version
                );
            }
        }

        $this->protocolVersions = \array_values($versions);
    }

    /**
     * Specify the request's HTTP method verb.
     */
    public function setMethod(string $method): void
    {
        parent::setMethod($method);
    }

    /**
     * Specify the request's HTTP URI.
     */
    public function setUri(UriInterface|string $uri): void
    {
        parent::setUri($uri instanceof UriInterface ? $uri : $this->createUriFromString($uri));
    }

    /**
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param non-empty-string $name Header name.
     * @param HeaderParamValueType $value Header value.
     */
    public function setHeader(string $name, array|string $value): void
    {
        if (($name[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::setHeader($name, $value);
    }

    /**
     * Assign a value for the specified header field by adding a header line.
     *
     * @param non-empty-string $name Header name.
     * @param HeaderParamValueType $value Header value.
     */
    public function addHeader(string $name, array|string $value): void
    {
        if (($name[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::addHeader($name, $value);
    }

    public function setHeaders(array $headers): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::setHeaders($headers);
    }

    public function replaceHeaders(array $headers): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::replaceHeaders($headers);
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $name Header name.
     */
    public function removeHeader(string $name): void
    {
        parent::removeHeader($name);
    }

    public function setQueryParameter(string $key, array|string|null $value): void
    {
        parent::setQueryParameter($key, $value);
    }

    public function addQueryParameter(string $key, array|string|null $value): void
    {
        parent::addQueryParameter($key, $value);
    }

    public function setQueryParameters(array $parameters): void
    {
        parent::setQueryParameters($parameters);
    }

    public function replaceQueryParameters(array $parameters): void
    {
        parent::replaceQueryParameters($parameters);
    }

    public function removeQueryParameter(string $key): void
    {
        parent::removeQueryParameter($key);
    }

    public function removeQuery(): void
    {
        parent::removeQuery();
    }

    /**
     * Retrieve the request body.
     */
    public function getBody(): HttpContent
    {
        return $this->body;
    }

    /**
     * Assign the message entity body.
     */
    public function setBody(HttpContent|string $body): void
    {
        $this->body = \is_string($body) ? BufferedContent::fromString($body) : $body;
    }

    /**
     * Registers a callback to the request that is invoked when the server pushes an additional resource.
     * The callback is given two parameters: the Request generated from the pushed resource, and a future for the
     * Response containing the pushed resource. An HttpException, StreamException, or CancelledException can be thrown
     * to refuse the push. If no callback is registered, pushes are automatically rejected.
     *
     * Interceptors can mostly use {@see interceptPush()} instead.
     *
     * Example:
     * function (Request $request, Future $future): void {
     *     $uri = $request->getUri(); // URI of pushed resource.
     *     $response = $future->await(); // Wait for resource to arrive.
     *     // Use Response object from completed future.
     * }
     *
     * @param null|\Closure(Request, Future): void $onPush
     */
    public function setPushHandler(?\Closure $onPush): void
    {
        $this->onPush = $onPush;
    }

    /**
     * Allows interceptors to modify also pushed responses.
     *
     * If no push closure has been set by the application, the interceptor won't be invoked. If you want to enable
     * push in an interceptor without the application setting a push handler, you need to use {@see setPushHandler()}.
     *
     * @param \Closure(Request, Response): Response $interceptor Receives the response and might modify it or return a
     * new instance.
     */
    public function interceptPush(\Closure $interceptor): void
    {
        if ($this->onPush === null) {
            return;
        }

        $onPush = $this->onPush;
        $this->onPush = static function (Request $request, Future $future) use ($onPush, $interceptor): void {
            $future = async(function () use ($request, $interceptor, $future): Response {
                $response = $future->await();
                return $interceptor($request, $response);
            });

            $onPush($request, $future);
        };
    }

    /**
     * @return null|\Closure(Request, Future): void
     */
    public function getPushHandler(): ?\Closure
    {
        return $this->onPush;
    }

    /**
     * Registers a callback invoked if a 101 response is returned to the request.
     *
     * @param null|\Closure(UpgradedSocket, Request, Response): void $onUpgrade
     */
    public function setUpgradeHandler(?\Closure $onUpgrade): void
    {
        $this->onUpgrade = $onUpgrade;
    }

    /**
     * @return null|\Closure(UpgradedSocket, Request, Response): void
     */
    public function getUpgradeHandler(): ?\Closure
    {
        return $this->onUpgrade;
    }

    /**
     * Registers a callback invoked when a 1xx response is returned to the request (other than a 101).
     *
     * @param null|\Closure(Response): void $onInformationalResponse
     */
    public function setInformationalResponseHandler(?\Closure $onInformationalResponse): void
    {
        $this->onInformationalResponse = $onInformationalResponse;
    }

    /**
     * @return null|\Closure(Response): void
     */
    public function getInformationalResponseHandler(): ?\Closure
    {
        return $this->onInformationalResponse;
    }

    /**
     * @return float Timeout in seconds for the TCP connection.
     */
    public function getTcpConnectTimeout(): float
    {
        return $this->tcpConnectTimeout;
    }

    /**
     * Set the timeout in seconds for establishing the TCP connection. Use 0 for no timeout.
     */
    public function setTcpConnectTimeout(float $tcpConnectTimeout): void
    {
        $this->tcpConnectTimeout = \max(0, $tcpConnectTimeout);
    }

    /**
     * @return float Timeout in seconds for the TLS handshake.
     */
    public function getTlsHandshakeTimeout(): float
    {
        return $this->tlsHandshakeTimeout;
    }

    /**
     * Set the timeout in seconds for the TLS handshake. Use 0 for no timeout.
     */
    public function setTlsHandshakeTimeout(float $tlsHandshakeTimeout): void
    {
        $this->tlsHandshakeTimeout = \max(0, $tlsHandshakeTimeout);
    }

    /**
     * @return float Timeout in seconds for the HTTP transfer (not counting TCP connect and TLS handshake).
     */
    public function getTransferTimeout(): float
    {
        return $this->transferTimeout;
    }

    /**
     * @param float $transferTimeout The timeout in seconds for the entire HTTP request transfer. Use 0 for no
     *      transfer timeout.
     */
    public function setTransferTimeout(float $transferTimeout): void
    {
        $this->transferTimeout = \max(0, $transferTimeout);
    }

    /**
     * @return float Timeout in seconds since the last data was received before the request fails due to inactivity.
     */
    public function getInactivityTimeout(): float
    {
        return $this->inactivityTimeout;
    }

    /**
     * @param float $inactivityTimeout The timeout in seconds since the last data was received before the request
     *      fails due to inactivity. Use 0 for no inactivity timeout.
     */
    public function setInactivityTimeout(float $inactivityTimeout): void
    {
        $this->inactivityTimeout = \max(0, $inactivityTimeout);
    }

    /**
     * @return non-negative-int Size limit of the response headers. Applies only to HTTP/1.x requests.
     *      0 indicates no limit.
     */
    public function getHeaderSizeLimit(): int
    {
        return $this->headerSizeLimit;
    }

    /**
     * @param int $headerSizeLimit The size limit of response headers. Applies only to HTTP/1.x requests.
     *      Use 0 for no limit. Default is 16 KiB.
     */
    public function setHeaderSizeLimit(int $headerSizeLimit): void
    {
        $this->headerSizeLimit = \max(0, $headerSizeLimit);
    }

    /**
     * @return int Size limit of the response body. 0 indicates no limit.
     */
    public function getBodySizeLimit(): int
    {
        return $this->bodySizeLimit;
    }

    /**
     * @param int $bodySizeLimit The size limit of the response body. Use 0 for no limit. Default is 10 MiB.
     */
    public function setBodySizeLimit(int $bodySizeLimit): void
    {
        $this->bodySizeLimit = \max(0, $bodySizeLimit);
    }

    /**
     * @return array<non-empty-string, mixed> An array of all request attributes in the request's local storage,
     *      indexed by name.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Check whether a variable with the given name exists in the request's local storage.
     *
     * Each request has its own local storage to which applications and interceptors may read and write data.
     * Other interceptors which are aware of this data can then access it without the server being tightly coupled to
     * specific implementations.
     *
     * @param non-empty-string $name Name of the attribute, should be namespaced with a vendor and package namespace
     *      like classes.
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
     * @param non-empty-string $name Name of the attribute, should be namespaced with a vendor and package namespace
     *      like classes.
     *
     * @throws MissingAttributeError If an attribute with the given name does not exist.
     */
    public function getAttribute(string $name): mixed
    {
        if (!$this->hasAttribute($name)) {
            throw new MissingAttributeError("The requested attribute '{$name}' does not exist");
        }

        return $this->attributes[$name];
    }

    /**
     * Assign a variable to the request's local storage.
     *
     * Each request has its own local storage to which applications and interceptors may read and write data.
     * Other interceptors which are aware of this data can then access it without the server being tightly coupled to
     * specific implementations.
     *
     * **Example**
     *
     * ```php
     * $request->setAttribute(Timing::class, $stopWatch);
     * ```
     *
     * @param non-empty-string $name Name of the attribute, should be namespaced with a vendor and package namespace
     *      like classes.
     * @param mixed $value Value of the attribute, might be any serializable value.
     */
    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Remove an attribute from the request's local storage.
     *
     * @param non-empty-string $name Name of the attribute, should be namespaced with a vendor and package namespace
     *      like classes.
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
        return \in_array($this->getMethod(), ['GET', 'HEAD', 'PUT', 'DELETE'], true);
    }

    private function createUriFromString(string $uri): UriInterface
    {
        return Uri\Http::new($uri);
    }
}
