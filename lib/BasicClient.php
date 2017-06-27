<?php

namespace Amp\Artax;

use Amp\Artax\Cookie\Cookie;
use Amp\Artax\Cookie\CookieFormatException;
use Amp\Artax\Cookie\CookieJar;
use Amp\Artax\Cookie\NullCookieJar;
use Amp\Artax\Cookie\PublicSuffixList;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\Message;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ZlibInputStream;
use Amp\CancellationToken;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Dns\ResolutionException;
use Amp\Emitter;
use Amp\Loop;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket\ClientSocket;
use Amp\Socket\ClientTlsContext;
use Amp\Success;
use Amp\Uri\InvalidUriException;
use Amp\Uri\Uri;
use function Amp\asyncCall;
use function Amp\call;

/**
 * Standard Client implementation.
 *
 * Use the Client interface for your type declarations so people can use composition to add layers like caching.
 *
 * @see Client
 */
final class BasicClient implements Client {
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; Artax)';

    private $cookieJar;
    private $socketPool;
    private $tlsContext;
    private $hasZlib;
    private $options = [
        self::OP_AUTO_ENCODING => true,
        self::OP_TRANSFER_TIMEOUT => 15000,
        self::OP_MAX_REDIRECTS => 5,
        self::OP_AUTO_REFERER => true,
        self::OP_DISCARD_BODY => false,
        self::OP_USER_AGENT => null,
        self::OP_MAX_HEADER_BYTES => Parser::DEFAULT_MAX_HEADER_BYTES,
        self::OP_MAX_BODY_BYTES => Parser::DEFAULT_MAX_BODY_BYTES,
    ];

    public function __construct(
        CookieJar $cookieJar = null,
        HttpSocketPool $socketPool = null,
        ClientTlsContext $tlsContext = null
    ) {
        $this->cookieJar = $cookieJar ?? new NullCookieJar;
        $this->tlsContext = $tlsContext ?? new ClientTlsContext;
        $this->socketPool = $socketPool ?? new HttpSocketPool;
        $this->hasZlib = extension_loaded('zlib');
    }

    /** @inheritdoc */
    public function request($uriOrRequest, array $options = [], CancellationToken $cancellation = null): Promise {
        return call(function () use ($uriOrRequest, $options, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;

            list($request, $uri) = $this->generateRequestFromUri($uriOrRequest);
            $options = $options ? array_merge($this->options, $options) : $this->options;

            $headers = yield $request->getBody()->getHeaders();
            foreach ($headers as $name => $header) {
                $request = $request->withHeader($name, $header);
            }

            $originalUri = $uri;
            $previousResponse = null;

            $maxRedirects = $options[self::OP_MAX_REDIRECTS];
            $requestNr = 1;

            do {
                /** @var Request $request */
                $request = yield from $this->normalizeRequestBodyHeaders($request);
                $request = $this->normalizeRequestEncodingHeaderForZlib($request, $options);
                $request = $this->normalizeRequestHostHeader($request, $uri);
                $request = $this->normalizeRequestUserAgent($request, $options);
                $request = $this->normalizeRequestAcceptHeader($request);
                $request = $this->assignApplicableRequestCookies($request);

                /** @var Response $response */
                $response = yield $this->doRequest($request, $uri, $options, $previousResponse, $cancellation);

                if ($redirectUri = $this->getRedirectUri($response)) {
                    // Discard response body of redirect responses
                    $body = $response->getBody();
                    while (null !== yield $body->read());

                    /**
                     * If this is a 302/303 we need to follow the location with a GET if the original request wasn't
                     * GET. Otherwise we need to send the body again.
                     *
                     * We won't resend the body nor any headers on redirects to other hosts for security reasons.
                     *
                     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.3
                     */
                    $method = $request->getMethod();
                    $status = $response->getStatus();
                    $isSameHost = $redirectUri->getAuthority(false) === $originalUri->getAuthority(false);

                    if ($isSameHost) {
                        $request = $request->withUri($redirectUri);

                        if ($status >= 300 && $status <= 303 && $method !== 'GET') {
                            $request = $request->withMethod('GET');
                            $request = $request->withoutHeader('Transfer-Encoding');
                            $request = $request->withoutHeader('Content-Length');
                            $request = $request->withoutHeader('Content-Type');
                            $request = $request->withBody(null);
                        }
                    } else {
                        // We ALWAYS follow with a GET and without any headers or the body for redirects to other hosts
                        $request = new Request((string) $redirectUri);
                    }

                    if ($options[self::OP_AUTO_REFERER]) {
                        $request = $this->assignRedirectRefererHeader($request, $originalUri, $redirectUri);
                    }

                    $previousResponse = $response;
                    $originalUri = $redirectUri;
                    $uri = $redirectUri;
                } else {
                    break;
                }
            } while (++$requestNr <= $maxRedirects + 1);

            if ($redirectUri = $this->getRedirectUri($response)) {
                throw new InfiniteRedirectException(
                    sprintf('Too many redirects detected while following Location header: %s', $redirectUri)
                );
            }

            return $response;
        });
    }

    private function doRequest(Request $request, Uri $uri, array $options, Response $previousResponse = null, CancellationToken $cancellation): Promise {
        $deferred = new Deferred;

        asyncCall(function () use ($deferred, $request, $uri, $options, $previousResponse, $cancellation) {
            try {
                yield from $this->doWrite($request, $uri, $options, $previousResponse, $deferred, $cancellation);
            } catch (HttpException $e) {
                if ($deferred) {
                    $deferred->fail($e);
                }
            }
        });

        return $deferred->promise();
    }

    private function generateRequestFromUri($uriOrRequest) {
        if (is_string($uriOrRequest)) {
            $uri = $this->buildUriFromString($uriOrRequest);
            $request = new Request($uri);
        } elseif ($uriOrRequest instanceof Request) {
            $uri = $this->buildUriFromString($uriOrRequest->getUri());
            $request = $uriOrRequest;
        } else {
            throw new HttpException(
                'Request must be a valid HTTP URI or Amp\Artax\Request instance'
            );
        }

        return [$request, $uri];
    }

    private function doRead(Request $request, Uri $uri, ClientSocket $socket, array $options, Response $previousResponse = null, Deferred &$deferred = null, CancellationToken $cancellation): \Generator {
        try {
            $bodyEmitter = new Emitter;
            $backpressure = new Success;

            $bodyCallback = static function ($data) use ($bodyEmitter, &$backpressure) {
                $backpressure = $bodyEmitter->emit($data);
            };

            if ($options[self::OP_DISCARD_BODY]) {
                $bodyCallback = null;
            }

            $parser = new Parser($bodyCallback);

            $parser->enqueueResponseMethodMatch($request->getMethod());
            $parser->setAllOptions([
                Parser::OP_MAX_HEADER_BYTES => $options[self::OP_MAX_HEADER_BYTES],
                Parser::OP_MAX_BODY_BYTES => $options[self::OP_MAX_BODY_BYTES],
            ]);

            $crypto = \stream_get_meta_data($socket->getResource())["crypto"] ?? null;
            $connectionInfo = new ConnectionInfo(
                $socket->getLocalAddress(),
                $socket->getRemoteAddress(),
                $crypto ? TlsInfo::fromMetaData($crypto) : null
            );

            $cancellation->throwIfRequested();

            while (($chunk = yield $socket->read()) !== null) {
                $cancellation->throwIfRequested();

                try {
                    $parseResult = $parser->parse($chunk);

                    if ($parseResult) {
                        $parseResult["headers"] = \array_change_key_case($parseResult["headers"], \CASE_LOWER);

                        $response = $this->finalizeResponse($request, $parseResult, new IteratorStream($bodyEmitter->iterate()), $previousResponse, $connectionInfo);

                        if ($deferred) {
                            $deferred->resolve($response);
                        }

                        // Required, otherwise responses without body hang
                        if ($parseResult["headersOnly"]) {
                            // Directly parse again in case we already have the full body but aborted parsing to resolve promise.
                            $chunk = null;

                            do {
                                $cancellation->throwIfRequested();

                                try {
                                    $parseResult = $parser->parse($chunk);
                                } catch (ParseException $e) {
                                    $bodyEmitter->fail($e);
                                    $bodyEmitter = null;
                                    throw $e;
                                }

                                if ($parseResult) {
                                    break;
                                }

                                yield $backpressure;
                            } while (($chunk = yield $socket->read()) !== null);
                        }

                        if ($this->shouldCloseSocketAfterResponse($response)) {
                            $this->socketPool->clear($socket);
                            $socket->close();
                        } else {
                            $this->socketPool->checkin($socket);
                        }

                        // Complete body AFTER socket checkin, so the socket can be reused for a potential redirect
                        $bodyEmitter->complete();
                        $bodyEmitter = null;

                        return;
                    }
                } catch (ParseException $e) {
                    $this->socketPool->clear($socket);
                    $socket->close();

                    if ($deferred) {
                        $deferred->fail($e);
                    }

                    return;
                }
            }

            $parserState = $parser->getState();

            if ($parserState === Parser::AWAITING_HEADERS && empty($retryCount)) {
                $this->doWrite($request, $uri, $options, $previousResponse, $deferred, $cancellation);
            } else {
                $exception = new SocketException(
                    sprintf(
                        'Socket disconnected prior to response completion (Parser state: %s)',
                        $parserState
                    )
                );

                if ($deferred) {
                    $deferred->fail($exception);
                }

                $deferred = null;

                if ($bodyEmitter) {
                    $bodyEmitter->fail($exception);
                }

                $bodyEmitter = null;

                $this->socketPool->clear($socket);
                $socket->close();
            }
        } catch (\Throwable $e) {
            if ($bodyEmitter) {
                $bodyEmitter->fail($e);
            }

            $this->socketPool->clear($socket);
            $socket->close();
            throw $e;
        }
    }

    private function doWrite(Request $request, Uri $uri, array $options, Response $previousResponse = null, Deferred &$deferred = null, CancellationToken $cancellation) {
        $authority = $this->generateAuthorityFromUri($uri);
        $socketCheckoutUri = $uri->getScheme() . "://{$authority}";

        try {
            /** @var ClientSocket $socket */
            $socket = yield $this->socketPool->checkout($socketCheckoutUri, $cancellation);
        } catch (ResolutionException $dnsException) {
            throw new DnsException(\sprintf("Resolving the specified domain failed: '%s'", $authority), 0, $dnsException);
        }

        try {
            if ($uri->getScheme() === 'https') {
                try {
                    yield $socket->enableCrypto($this->tlsContext->withPeerName($uri->getHost()));
                } catch (\Throwable $exception) {
                    // If crypto failed we make sure the socket pool gets rid of its reference
                    // to this socket connection.
                    $this->socketPool->clear($socket);
                    throw $exception;
                }
            }

            $timeout = $options[self::OP_TRANSFER_TIMEOUT];

            if ($timeout > 0) {
                $transferTimeoutWatcher = Loop::delay($timeout, function () use (&$deferred, $timeout, $socket) {
                    if ($deferred === null) {
                        return;
                    }

                    $tmp = $deferred;
                    $deferred = null;
                    $tmp->fail(new TimeoutException(
                        sprintf('Allowed transfer timeout exceeded: %d ms', $timeout)
                    ));

                    $this->socketPool->clear($socket);
                    $socket->close();
                });

                $deferred->promise()->onResolve(static function () use ($transferTimeoutWatcher) {
                    Loop::cancel($transferTimeoutWatcher);
                });
            }

            $readingPromise = new Coroutine($this->doRead($request, $uri, $socket, $options, $previousResponse, $deferred, $cancellation));
            $readingPromise->onResolve(function ($error) use (&$deferred) {
                if ($error && $deferred) {
                    $deferred->fail($error);
                    $deferred = null;
                }
            });

            yield $socket->write($this->generateRawRequestHeaders($request));

            $body = $request->getBody()->createBodyStream();
            $chunking = !$request->hasHeader("content-length");

            while (($chunk = yield $body->read()) !== null) {
                $cancellation->throwIfRequested();

                if ($chunk === "") {
                    continue;
                }

                if ($chunking) {
                    $chunk = \dechex(\strlen($chunk)) . "\r\n" . $chunk . "\r\n";
                }

                yield $socket->write($chunk);
            }

            if ($chunking) {
                yield $socket->write("0\r\n\r\n");
            }
        } catch (\Throwable $e) {
            $this->socketPool->clear($socket);
            $socket->close();
            throw $e;
        }
    }

    private function buildUriFromString($str): Uri {
        try {
            $uri = new Uri($str);
            $scheme = $uri->getScheme();

            if (($scheme === "http" || $scheme === "https") && $uri->getHost()) {
                return $uri;
            }

            throw new HttpException("Request must specify a valid HTTP URI");
        } catch (InvalidUriException $e) {
            throw new HttpException("Request must specify a valid HTTP URI", 0, $e);
        }
    }

    private function normalizeRequestBodyHeaders(Request $request): \Generator {
        $method = $request->getMethod();

        if ($method === 'TRACE' || $method === 'HEAD' || $method === 'OPTIONS') {
            /** @var Request $request */
            $request = $request->withBody(null);
        }

        /** @var AggregateBody $body */
        $body = $request->getBody();
        $bodyLength = yield $body->getBodyLength();

        if ($bodyLength === 0) {
            $request = $request->withHeader('Content-Length', '0');
            $request = $request->withoutHeader('Transfer-Encoding');
        } else {
            if ($bodyLength > 0) {
                $request = $request->withHeader("Content-Length", $bodyLength);
                $request = $request->withoutHeader("Transfer-Encoding");
            } else {
                $request = $request->withHeader("Transfer-Encoding", "chunked");
            }
        }

        return $request;
    }

    private function normalizeRequestEncodingHeaderForZlib(Request $request, array $options): Request {
        $autoEncoding = $options[self::OP_AUTO_ENCODING];

        if (!$autoEncoding) {
            return $request;
        }

        if ($this->hasZlib) {
            return $request->withHeader('Accept-Encoding', 'gzip, deflate, identity');
        }

        return $request->withoutHeader('Accept-Encoding');
    }

    private function normalizeRequestHostHeader(Request $request, Uri $uri): Request {
        if ($request->hasHeader('Host')) {
            return $request;
        }

        $authority = $this->generateAuthorityFromUri($uri);
        $request = $request->withHeader('Host', $this->normalizeHostHeader($authority));

        return $request;
    }

    private function normalizeHostHeader(string $host): string {
        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80 or 443
        if (strpos($host, ':80') === strlen($host) - 3) {
            return substr($host, 0, -3);
        } elseif (strpos($host, ':443') === strlen($host) - 4) {
            return substr($host, 0, -4);
        }

        return $host;
    }

    private function normalizeRequestUserAgent(Request $request, array $options): Request {
        if ($request->hasHeader('User-Agent')) {
            return $request;
        }

        $userAgent = $options[self::OP_USER_AGENT] ?? self::DEFAULT_USER_AGENT;
        return $request->withHeader('User-Agent', $userAgent);
    }

    private function normalizeRequestAcceptHeader(Request $request): Request {
        if ($request->hasHeader('Accept')) {
            return $request;
        }

        return $request->withHeader('Accept', '*/*');
    }

    private function assignApplicableRequestCookies(Request $request): Request {
        $uri = new Uri($request->getUri());

        $domain = $uri->getHost();
        $path = $uri->getPath();

        if (!$applicableCookies = $this->cookieJar->get($domain, $path)) {
            // No cookies matched our request; we're finished.
            return $request->withoutHeader("Cookie");
        }

        $isRequestSecure = strcasecmp($uri->getScheme(), "https") === 0;
        $cookiePairs = [];

        /** @var Cookie $cookie */
        foreach ($applicableCookies as $cookie) {
            if (!$cookie->isSecure() || $isRequestSecure) {
                $cookiePairs[] = $cookie->getName() . "=" . $cookie->getValue();
            }
        }

        if ($cookiePairs) {
            return $request->withHeader("Cookie", \implode("; ", $cookiePairs));
        }

        return $request->withoutHeader("Cookie");
    }

    private function generateAuthorityFromUri(Uri $uri): string {
        $host = $uri->getHost();
        $port = $uri->getPort();

        return "{$host}:{$port}";
    }

    private function finalizeResponse(Request $request, array $parserResult, InputStream $body, Response $previousResponse = null, ConnectionInfo $connectionInfo) {
        if ($encoding = $this->determineCompressionEncoding($parserResult["headers"])) {
            $body = new ZlibInputStream($body, $encoding);
        }

        // Wrap the input stream so we can discard the body in case it's destructed but hasn't been consumed.
        // This allows reusing the connection for further requests. It's important to have __destruct in InputStream and
        // not in Message, because an InputStream might be pulled out of Message and used separately.
        $body = new class($body) implements InputStream {
            private $body;

            public function __construct(InputStream $body) {
                $this->body = $body;
            }

            public function read(): Promise {
                return $this->body->read();
            }

            public function __destruct() {
                asyncCall(function () {
                    try {
                        while (null !== yield $this->body->read());
                    } catch (StreamException $e) {
                        // ignore any exceptions, we're just discarding
                    }
                });
            }
        };

        $response = new class($parserResult["protocol"], $parserResult["status"], $parserResult["reason"], $parserResult["headers"], $body, $request, $previousResponse, new MetaInfo($connectionInfo)) implements Response {
            private $protocolVersion;
            private $status;
            private $reason;
            private $request;
            private $previousResponse;
            private $headers;
            private $body;
            private $metaInfo;

            public function __construct(
                string $protocolVersion,
                int $status,
                string $reason,
                array $headers,
                InputStream $body,
                Request $request,
                Response $previousResponse = null,
                MetaInfo $metaInfo
            ) {
                $this->protocolVersion = $protocolVersion;
                $this->status = $status;
                $this->reason = $reason;
                $this->headers = $headers;
                $this->body = new Message($body);
                $this->request = $request;
                $this->previousResponse = $previousResponse;
                $this->metaInfo = $metaInfo;
            }

            public function getProtocolVersion(): string {
                return $this->protocolVersion;
            }

            public function getStatus(): int {
                return $this->status;
            }

            public function getReason(): string {
                return $this->reason;
            }

            public function getRequest(): Request {
                return $this->request;
            }

            public function getOriginalRequest(): Request {
                if (empty($this->previousResponse)) {
                    return $this->request;
                }

                return $this->previousResponse->getOriginalRequest();
            }

            public function getPreviousResponse() {
                return $this->previousResponse;
            }

            public function hasHeader(string $field): bool {
                return isset($this->headers[\strtolower($field)]);
            }

            public function getHeader(string $field) {
                return $this->headers[\strtolower($field)][0] ?? null;
            }

            public function getHeaderArray(string $field): array {
                return $this->headers[\strtolower($field)] ?? [];
            }

            public function getAllHeaders(): array {
                return $this->headers;
            }

            public function getBody(): Message {
                return $this->body;
            }

            public function getMetaInfo(): MetaInfo {
                return $this->metaInfo;
            }
        };

        if ($response->hasHeader('Set-Cookie')) {
            $requestDomain = (new Uri($request->getUri()))->getHost();
            $cookies = $response->getHeaderArray('Set-Cookie');

            foreach ($cookies as $rawCookieStr) {
                $this->storeResponseCookie($requestDomain, $rawCookieStr);
            }
        }

        return $response;
    }

    private function shouldCloseSocketAfterResponse(Response $response) {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('Connection');
        $responseConnHeader = $response->getHeader('Connection');

        if ($requestConnHeader && !strcasecmp($requestConnHeader, 'close')) {
            return true;
        } elseif ($responseConnHeader && !strcasecmp($responseConnHeader, 'close')) {
            return true;
        } elseif ($response->getProtocolVersion() === '1.0' && !$responseConnHeader) {
            return true;
        }

        return false;
    }

    private function determineCompressionEncoding(array $responseHeaders): int {
        if (!$this->hasZlib) {
            return 0;
        }

        if (!isset($responseHeaders["content-encoding"])) {
            return 0;
        }

        $contentEncodingHeader = \trim(\current($responseHeaders["content-encoding"]));

        if (strcasecmp($contentEncodingHeader, 'gzip') === 0) {
            return \ZLIB_ENCODING_GZIP;
        }

        if (strcasecmp($contentEncodingHeader, 'deflate') === 0) {
            return \ZLIB_ENCODING_DEFLATE;
        }

        return 0;
    }

    private function storeResponseCookie(string $requestDomain, string $rawCookieStr) {
        try {
            $cookie = Cookie::fromString($rawCookieStr);

            if (!$cookie->getDomain()) {
                $cookie = $cookie->withDomain($requestDomain);
            } else {
                // https://tools.ietf.org/html/rfc6265#section-4.1.2.3
                $cookieDomain = $cookie->getDomain();

                // If a domain is set, left dots are ignored and it's always a wildcard
                $cookieDomain = \ltrim($cookieDomain, ".");

                if ($cookieDomain !== $requestDomain) {
                    // ignore cookies on domains that are public suffixes
                    if (PublicSuffixList::isPublicSuffix($cookieDomain)) {
                        return;
                    }

                    // cookie origin would not be included when sending the cookie
                    if (\substr($requestDomain, 0, -\strlen($cookieDomain) - 1) . "." . $cookieDomain !== $requestDomain) {
                        return;
                    }
                }

                // always add the dot, it's used internally for wildcard matching when an explicit domain is sent
                $cookie = $cookie->withDomain("." . $cookieDomain);
            }

            $this->cookieJar->store($cookie);
        } catch (CookieFormatException $e) {
            // Ignore malformed Set-Cookie headers
        }
    }

    private function getRedirectUri(Response $response) {
        if (!$response->hasHeader('Location')) {
            return null;
        }

        $request = $response->getRequest();

        $status = $response->getStatus();
        $method = $request->getMethod();

        if ($status < 300 || $status > 399 || $method === 'HEAD') {
            return null;
        }

        $requestUri = new Uri($request->getUri());
        $redirectLocation = $response->getHeader('Location');

        try {
            return $requestUri->resolve($redirectLocation);
        } catch (InvalidUriException $e) {
            return null;
        }
    }

    /**
     * Clients must not add a Referer header when leaving an unencrypted resource and redirecting to an encrypted
     * resource.
     *
     * @param Request $request
     * @param string  $refererUri
     * @param string  $newUri
     *
     * @return Request
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec15.html#sec15.1.3
     */
    private function assignRedirectRefererHeader(Request $request, string $refererUri, string $newUri): Request {
        if (!$refererIsEncrypted = (\stripos($refererUri, 'https') === 0)) {
            return $request->withHeader('Referer', $refererUri);
        } elseif ($destinationIsEncrypted = (\stripos($newUri, 'https') === 0)) {
            return $request->withHeader('Referer', $refererUri);
        }

        return $request->withoutHeader('Referer');
    }

    /**
     * @param Request $request
     *
     * @return string
     *
     * @TODO Send absolute URIs in the request line when using a proxy server
     *       Right now this doesn't matter because all proxy requests use a CONNECT
     *       tunnel but this likely will not always be the case.
     */
    private function generateRawRequestHeaders(Request $request): string {
        $uri = $request->getUri();
        $uri = new Uri($uri);

        $requestUri = $uri->getPath() ?: '/';

        if ($query = $uri->getQuery()) {
            $requestUri .= '?' . $query;
        }

        $head = $request->getMethod() . ' ' . $requestUri . ' HTTP/' . $request->getProtocolVersion() . "\r\n";

        foreach ($request->getAllHeaders() as $field => $values) {
            if (\strcspn($field, "\r\n") !== \strlen($field)) {
                throw new HttpException("Blocked header injection attempt for header '{$field}'");
            }

            foreach ($values as $value) {
                if (\strcspn($value, "\r\n") !== \strlen($value)) {
                    throw new HttpException("Blocked header injection attempt for header '{$field}' with value '{$value}'");
                }

                $head .= "{$field}: {$value}\r\n";
            }
        }

        $head .= "\r\n";

        return $head;
    }

    /**
     * Set multiple options at once.
     *
     * @param array $options An array of the form [OP_CONSTANT => $value]
     *
     * @throws \Error on Unknown option key
     */
    public function setAllOptions(array $options) {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }
    }

    /**
     * Set an individual option.
     *
     * @param int   $option A Client option constant
     * @param mixed $value The option value to assign
     *
     * @throws \Error On unknown option key
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_AUTO_ENCODING:
                $this->options[self::OP_AUTO_ENCODING] = (bool) $value;
                break;
            case self::OP_TRANSFER_TIMEOUT:
                $this->options[self::OP_TRANSFER_TIMEOUT] = (int) $value;
                break;
            case self::OP_MAX_REDIRECTS:
                $this->options[self::OP_MAX_REDIRECTS] = (int) $value;
                break;
            case self::OP_AUTO_REFERER:
                $this->options[self::OP_AUTO_REFERER] = (bool) $value;
                break;
            case self::OP_DISCARD_BODY:
                $this->options[self::OP_DISCARD_BODY] = (bool) $value;
                break;
            case self::OP_USER_AGENT:
                $this->options[self::OP_USER_AGENT] = (string) $value;
                break;
            case self::OP_MAX_HEADER_BYTES:
                $this->options[self::OP_MAX_HEADER_BYTES] = (int) $value;
                break;
            case self::OP_MAX_BODY_BYTES:
                $this->options[self::OP_MAX_BODY_BYTES] = (int) $value;
                break;
            default:
                throw new \Error(
                    sprintf("Unknown option: %s", $option)
                );
        }
    }
}
