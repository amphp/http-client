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
use Amp\ByteStream\ZlibInputStream;
use Amp\Coroutine;
use Amp\Deferred;
use Amp\Dns\ResolutionException;
use Amp\Emitter;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\Socket;
use Amp\Socket\SocketPool;
use Amp\Uri\Uri;
use function Amp\asyncCall;
use function Amp\call;

class Client implements HttpClient {
    const USER_AGENT = 'Mozilla/5.0 (compatible; Artax)';

    const OP_BINDTO = SocketPool::OP_BINDTO;
    const OP_CONNECT_TIMEOUT = SocketPool::OP_CONNECT_TIMEOUT;
    const OP_KEEP_ALIVE_TIMEOUT = SocketPool::OP_IDLE_TIMEOUT;
    const OP_PROXY_HTTP = HttpSocketPool::OP_PROXY_HTTP;
    const OP_PROXY_HTTPS = HttpSocketPool::OP_PROXY_HTTPS;
    const OP_AUTO_ENCODING = 'amp.artax.client.auto-encoding';
    const OP_TRANSFER_TIMEOUT = 'amp.artax.client.transfer-timeout';
    const OP_FOLLOW_LOCATION = 'amp.artax.client.follow-location';
    const OP_AUTO_REFERER = 'amp.artax.client.auto-referer';
    const OP_DISCARD_BODY = 'amp.artax.client.discard-body';
    const OP_IO_GRANULARITY = 'amp.artax.client.io-granularity';
    const OP_CRYPTO = 'amp.artax.client.crypto';
    const OP_DEFAULT_USER_AGENT = 'amp.artax.client.default-user-agent';
    const OP_MAX_HEADER_BYTES = 'amp.artax.client.max-header-bytes';
    const OP_MAX_BODY_BYTES = 'amp.artax.client.max-body-bytes';

    private $cookieJar;
    private $socketPool;
    private $hasZlib;
    private $options = [
        self::OP_BINDTO => '',
        self::OP_CONNECT_TIMEOUT => 10000,
        self::OP_KEEP_ALIVE_TIMEOUT => 10000,
        self::OP_PROXY_HTTP => '',
        self::OP_PROXY_HTTPS => '',
        self::OP_AUTO_ENCODING => true,
        self::OP_TRANSFER_TIMEOUT => 15000,
        self::OP_FOLLOW_LOCATION => true,
        self::OP_AUTO_REFERER => true,
        self::OP_DISCARD_BODY => false,
        self::OP_IO_GRANULARITY => 32768,
        self::OP_CRYPTO => [],
        self::OP_DEFAULT_USER_AGENT => null,
        self::OP_MAX_HEADER_BYTES => Parser::DEFAULT_MAX_HEADER_BYTES,
        self::OP_MAX_BODY_BYTES => Parser::DEFAULT_MAX_BODY_BYTES,
    ];

    public function __construct(CookieJar $cookieJar = null, HttpSocketPool $socketPool = null) {
        $this->cookieJar = $cookieJar ?? new NullCookieJar;
        $this->socketPool = $socketPool ?? new HttpSocketPool;
        $this->hasZlib = extension_loaded('zlib');
    }

    /**
     * Asynchronously request an HTTP resource.
     *
     * @param Request|string An HTTP URI string or a Request instance
     * @param array          $options An array specifying options applicable only for this request
     *
     * @return Promise A promise to resolve the request at some point in the future
     */
    public function request($uriOrRequest, array $options = []): Promise {
        return call(function () use ($uriOrRequest, $options) {
            list($request, $uri) = $this->generateRequestFromUri($uriOrRequest);
            $options = $options ? array_merge($this->options, $options) : $this->options;

            $headers = yield $request->getBody()->getHeaders();
            foreach ($headers as $name => $header) {
                $request = $request->withHeader($name, $header);
            }

            $request = yield from $this->normalizeRequestBodyHeaders($request, $options);
            $request = $this->normalizeRequestEncodingHeaderForZlib($request, $options);
            $request = $this->normalizeRequestHostHeader($request, $uri);
            $request = $this->normalizeRequestUserAgent($request, $options);
            $request = $this->normalizeRequestAcceptHeader($request);
            $request = $this->assignApplicableRequestCookies($request);

            /** @var Response $response */
            $response = yield $this->doRequest($request, $uri, $options);

            if ($options[self::OP_FOLLOW_LOCATION]) {
                $retry = 0;

                $originalUri = $uri;
                $originalHost = $this->normalizeHostHeader($this->generateAuthorityFromUri($originalUri));

                // TODO: Add max-redirects option
                while (++$retry < 10 && $redirectUri = $this->getRedirectUri($response)) {
                    $refererUri = $request->getUri();

                    $request = $request->withUri($redirectUri);

                    $authority = $this->generateAuthorityFromUri($redirectUri);
                    $host = $this->normalizeHostHeader($authority);
                    $request = $request->withHeader('Host', $host);

                    // Don't leak any cookies to other origins
                    $request = $request->withoutHeader("Cookie");
                    $request = $this->assignApplicableRequestCookies($request);

                    if ($host !== $originalHost) {
                        // Don't leak any authorization data to other origins
                        // This does intentionally keep the authorization header for http ←→ https redirects
                        $request = $request->withoutHeader("Authorization");
                    }

                    /**
                     * If this is a 302/303 we need to follow the location with a GET if the
                     * original request wasn't GET. Otherwise we need to send the body again.
                     *
                     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.3
                     */
                    $method = $request->getMethod();
                    $status = $response->getStatus();

                    if ($status >= 300 && $status <= 303 && $method !== 'GET') {
                        $request = $request->withMethod('GET');
                        $request = $request->withoutHeader('Transfer-Encoding');
                        $request = $request->withoutHeader('Content-Length');
                        $request = $request->withoutHeader('Content-Type');
                        $request = $request->withBody(null);
                    }

                    if ($options[self::OP_AUTO_REFERER]) {
                        $request = $this->assignRedirectRefererHeader($request, $refererUri, $redirectUri);
                    }

                    $response = yield $this->doRequest($request, $redirectUri, $options, $response);
                }

                if ($retry === 10 && $redirectUri = $this->getRedirectUri($response)) {
                    throw new InfiniteRedirectException(
                        sprintf('Infinite redirect detected while following Location header: %s', $redirectUri)
                    );
                }
            }

            return $response;
        });
    }

    private function doRequest(Request $request, Uri $uri, array $options, Response $previousResponse = null): Promise {
        $deferred = new Deferred;

        asyncCall(function () use (&$deferred, $request, $uri, $options, $previousResponse) {
            try {
                yield from $this->doWrite($request, $uri, $options, $previousResponse, $deferred);
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

    private function doRead(Request $request, Uri $uri, Socket $socket, array $options, Response $previousResponse = null, Deferred &$deferred = null) {
        $bodyEmitter = new Emitter;

        $bodyCallback = static function ($data) use ($bodyEmitter) {
            $bodyEmitter->emit($data);
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

        while (($chunk = yield $socket->read()) !== null) {
            try {
                $parseResult = $parser->parse($chunk);

                if ($parseResult) {
                    $parseResult["headers"] = \array_change_key_case($parseResult["headers"], \CASE_LOWER);

                    $response = $this->finalizeResponse($request, $parseResult, new IteratorStream($bodyEmitter->iterate()), $previousResponse);

                    if ($deferred) {
                        $deferred->resolve($response);
                    }

                    // Required, otherwise responses without body hang
                    if ($parseResult["headersOnly"]) {
                        // Directly parse again in case we already have the full body but aborted parsing to resolve promise.
                        $chunk = null;

                        do {
                            try {
                                $parseResult = $parser->parse($chunk);
                            } catch (ParseException $e) {
                                $bodyEmitter->fail($e);
                                throw $e;
                            }

                            if ($parseResult) {
                                break;
                            }
                        } while (($chunk = yield $socket->read()) !== null);
                    }

                    $bodyEmitter->complete();
                    $bodyEmitter = null;

                    if ($this->shouldCloseSocketAfterResponse($response)) {
                        $this->socketPool->clear($socket->getResource());
                        $socket->close();
                    } else {
                        $this->socketPool->checkin($socket->getResource());
                    }

                    return;
                }
            } catch (ParseException $e) {
                if ($deferred) {
                    $this->socketPool->clear($socket->getResource());
                    $socket->close();
                    $deferred->fail($e);
                }

                break;
            }
        }

        $parserState = $parser->getState();

        if ($parserState === Parser::AWAITING_HEADERS && empty($retryCount)) {
            $this->doWrite($request, $uri, $options, $previousResponse, $deferred);
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

            $this->socketPool->clear($socket->getResource());
            $socket->close();
        }
    }

    private function doWrite(Request $request, Uri $uri, array $options, Response $previousResponse = null, Deferred &$deferred = null) {
        $authority = $this->generateAuthorityFromUri($uri);
        $socketCheckoutUri = $uri->getScheme() . "://{$authority}";

        try {
            $rawSocket = yield $this->socketPool->checkout($socketCheckoutUri, $options);
        } catch (ResolutionException $dnsException) {
            throw new DnsException(\sprintf("Resolving the specified domain failed: '%s'", $authority), 0, $dnsException);
        }

        $socket = new Socket($rawSocket);

        if ($uri->getScheme() === 'https') {
            $cryptoOptions = $this->generateCryptoOptions($uri, $options);

            try {
                yield $socket->enableCrypto($cryptoOptions);
            } catch (\Throwable $exception) {
                // If crypto failed we make sure the socket pool gets rid of its reference
                // to this socket connection.
                $this->socketPool->clear($rawSocket);
                throw $exception;
            }
        }

        $timeout = $options[self::OP_TRANSFER_TIMEOUT];

        if ($timeout > 0) {
            // TODO: Abort request
            $transferTimeoutWatcher = Loop::delay($timeout, static function () use (&$deferred, $timeout) {
                if ($deferred === null) {
                    return;
                }

                $tmp = $deferred;
                $deferred = null;
                $tmp->fail(new TimeoutException(
                    sprintf('Allowed transfer timeout exceeded: %d ms', $timeout)
                ));
            });

            $deferred->promise()->onResolve(static function () use ($transferTimeoutWatcher) {
                Loop::cancel($transferTimeoutWatcher);
            });
        }

        Promise\rethrow(new Coroutine($this->doRead($request, $uri, $socket, $options, $previousResponse, $deferred)));

        yield $socket->write($this->generateRawRequestHeaders($request));

        $body = $request->getBody()->createBodyStream();
        $chunking = !$request->hasHeader("content-length");

        while (($chunk = yield $body->read()) !== null) {
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
    }

    private function buildUriFromString($str): Uri {
        try {
            $uri = new Uri($str);
            $scheme = $uri->getScheme();

            if (($scheme === "http" || $scheme === "https") && $uri->getHost()) {
                return $uri;
            }

            throw new HttpException(
                'Request must specify a valid HTTP URI'
            );
        } catch (\Error $e) {
            throw new HttpException(
                $msg = 'Request must specify a valid HTTP URI',
                0,
                $e
            );
        }
    }

    private function normalizeRequestBodyHeaders(Request $request, array $options): \Generator {
        $method = $request->getMethod();

        if ($method === 'TRACE' || $method === 'HEAD' || $method === 'OPTIONS') {
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
            $request = $request->withHeader('Accept-Encoding', 'gzip, deflate, identity');
        } else {
            $request = $request->withoutHeader('Accept-Encoding');
        }

        return $request;
    }

    private function normalizeRequestHostHeader(Request $request, Uri $uri): Request {
        if ($request->hasHeader('Host')) {
            return $request;
        }

        $authority = $this->generateAuthorityFromUri($uri);
        $request = $request->withHeader('Host', $this->normalizeHostHeader($authority));

        return $request;
    }

    private function normalizeHostHeader($host): string {
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
        if (!$request->hasHeader('User-Agent')) {
            $userAgent = $options[self::OP_DEFAULT_USER_AGENT] ?? self::USER_AGENT;
            $request = $request->withHeader('User-Agent', $userAgent);
        }

        return $request;
    }

    private function normalizeRequestAcceptHeader(Request $request): Request {
        if (!$request->hasHeader('Accept')) {
            $request = $request->withHeader('Accept', '*/*');
        }

        return $request;
    }

    private function assignApplicableRequestCookies(Request $request): Request {
        $urlParts = \parse_url($request->getUri());

        $domain = $urlParts['host'];
        $path = isset($urlParts['path']) ? $urlParts['path'] : '/';

        if (!$applicableCookies = $this->cookieJar->get($domain, $path)) {
            // No cookies matched our request; we're finished.
            return $request;
        }

        $isRequestSecure = strcasecmp($urlParts['scheme'], 'https') === 0;
        $cookiePairs = [];

        /** @var Cookie $cookie */
        foreach ($applicableCookies as $cookie) {
            if (!$cookie->isSecure() || $isRequestSecure) {
                $cookiePairs[] = $cookie->getName() . '=' . $cookie->getValue();
            }
        }

        if ($cookiePairs) {
            $request = $request->withHeader('Cookie', \implode('; ', $cookiePairs));
        }

        return $request;
    }

    private function generateAuthorityFromUri(Uri $uri): string {
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();

        if (empty($port)) {
            $port = $scheme === 'https' ? 443 : 80;
        }

        return "{$host}:{$port}";
    }

    private function generateCryptoOptions(Uri $uri, array $options): array {
        $cryptoOptions = ['peer_name' => $uri->getHost()];

        // Allow client-wide TLS settings
        if ($this->options[self::OP_CRYPTO]) {
            $cryptoOptions = array_merge($cryptoOptions, $this->options[self::OP_CRYPTO]);
        }

        // Allow per-request TLS settings
        if ($options[self::OP_CRYPTO]) {
            $cryptoOptions = array_merge($cryptoOptions, $options[self::OP_CRYPTO]);
        }

        return $cryptoOptions;
    }

    private function finalizeResponse(Request $request, array $parserResult, InputStream $responseBody, Response $previousResponse = null) {
        if ($encoding = $this->determineCompressionEncoding($parserResult["headers"])) {
            $responseBody = new ZlibInputStream($responseBody, $encoding);
        }

        $response = new Response(
            $parserResult["protocol"],
            $parserResult["status"],
            $parserResult["reason"],
            $parserResult["headers"],
            new Message($responseBody),
            $request,
            $previousResponse
        );

        if ($response->hasHeader('Set-Cookie')) {
            $requestDomain = parse_url($request->getUri(), PHP_URL_HOST);
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

        $contentEncodingHeader = trim(current($responseHeaders["content-encoding"]));

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

        if (!$requestUri->canResolve($redirectLocation)) {
            return null;
        }

        return $requestUri->resolve($redirectLocation);
    }

    /**
     * Clients must not add a Referer header when leaving an unencrypted resource
     * and redirecting to an encrypted resource.
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
            foreach ($values as $value) {
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
            case self::OP_CONNECT_TIMEOUT:
                $this->options[self::OP_CONNECT_TIMEOUT] = $value;
                break;
            case self::OP_KEEP_ALIVE_TIMEOUT:
                $this->options[self::OP_KEEP_ALIVE_TIMEOUT] = $value;
                break;
            case self::OP_TRANSFER_TIMEOUT:
                $this->options[self::OP_TRANSFER_TIMEOUT] = (int) $value;
                break;
            case self::OP_FOLLOW_LOCATION:
                $this->options[self::OP_FOLLOW_LOCATION] = (bool) $value;
                break;
            case self::OP_AUTO_REFERER:
                $this->options[self::OP_AUTO_REFERER] = (bool) $value;
                break;
            case self::OP_DISCARD_BODY:
                $this->options[self::OP_DISCARD_BODY] = (bool) $value;
                break;
            case self::OP_IO_GRANULARITY:
                $value = (int) $value;
                $this->options[self::OP_IO_GRANULARITY] = $value > 0 ? $value : 32768;
                break;
            case self::OP_BINDTO:
                $this->options[self::OP_BINDTO] = $value;
                break;
            case self::OP_PROXY_HTTP:
                $this->options[self::OP_PROXY_HTTP] = $value;
                break;
            case self::OP_PROXY_HTTPS:
                $this->options[self::OP_PROXY_HTTPS] = $value;
                break;
            case self::OP_CRYPTO:
                $this->options[self::OP_CRYPTO] = (array) $value;
                break;
            case self::OP_DEFAULT_USER_AGENT:
                $this->options[self::OP_DEFAULT_USER_AGENT] = (string) $value;
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
