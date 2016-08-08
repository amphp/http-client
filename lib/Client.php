<?php

namespace Amp\Artax;

use Amp\Deferred,
    Amp\Socket as socket,
    Amp\Artax\Cookie\Cookie,
    Amp\Artax\Cookie\CookieJar,
    Amp\Artax\Cookie\ArrayCookieJar,
    Amp\Artax\Cookie\CookieParser;

class Client implements HttpClient {
    const USER_AGENT = 'Amp\Artax/1.0.0-dev (PHP)';

    const OP_BINDTO = "bind_to";
    const OP_MS_CONNECT_TIMEOUT = "timeout";
    const OP_HOST_CONNECTION_LIMIT = SocketPool::OP_HOST_CONNECTION_LIMIT;
    const OP_MS_KEEP_ALIVE_TIMEOUT = SocketPool::OP_MS_IDLE_TIMEOUT;
    const OP_PROXY_HTTP = HttpSocketPool::OP_PROXY_HTTP;
    const OP_PROXY_HTTPS = HttpSocketPool::OP_PROXY_HTTPS;
    const OP_AUTO_ENCODING = 'op.auto-encoding';
    const OP_MS_TRANSFER_TIMEOUT = 'op.ms-transfer-timeout';
    const OP_MS_100_CONTINUE_TIMEOUT = 'op.ms-100-continue-timeout';
    const OP_EXPECT_CONTINUE = 'op.expect-continue';
    const OP_FOLLOW_LOCATION = 'op.follow-location';
    const OP_AUTO_REFERER = 'op.auto-referer';
    const OP_BUFFER_BODY = 'op.buffer-body';
    const OP_DISCARD_BODY = 'op.discard-body';
    const OP_IO_GRANULARITY = 'op.io-granularity';
    const OP_VERBOSITY = 'op.verbosity';
    const OP_COMBINE_COOKIES = 'op.combine-cookies';
    const OP_CRYPTO = 'op.crypto';
    const OP_DEFAULT_USER_AGENT = 'op.default-user-agent';
    const OP_BODY_SWAP_SIZE = 'op.body-swap-size';
    const OP_MAX_HEADER_BYTES = 'op.max-header-bytes';
    const OP_MAX_BODY_BYTES = 'op.max-body-bytes';

    const VERBOSE_NONE = 0b00;
    const VERBOSE_SEND = 0b01;
    const VERBOSE_READ = 0b10;
    const VERBOSE_ALL  = 0b11;

    private $cookieJar;
    private $socketPool;
    private $writerFactory;
    private $hasZlib;
    private $queue;
    private $dequeuer;
    private $options = [
        self::OP_BINDTO => '',
        self::OP_MS_CONNECT_TIMEOUT => 30000,
        self::OP_HOST_CONNECTION_LIMIT => 8,
        self::OP_MS_KEEP_ALIVE_TIMEOUT => 10000,
        self::OP_PROXY_HTTP => '',
        self::OP_PROXY_HTTPS => '',
        self::OP_AUTO_ENCODING => true,
        self::OP_MS_TRANSFER_TIMEOUT => 120000,
        self::OP_MS_100_CONTINUE_TIMEOUT => 3000,
        self::OP_EXPECT_CONTINUE => false,
        self::OP_FOLLOW_LOCATION => true,
        self::OP_AUTO_REFERER => true,
        self::OP_BUFFER_BODY => true,
        self::OP_DISCARD_BODY => false,
        self::OP_IO_GRANULARITY => 32768,
        self::OP_VERBOSITY => self::VERBOSE_NONE,
        self::OP_COMBINE_COOKIES => true,
        self::OP_CRYPTO => [],
        self::OP_DEFAULT_USER_AGENT => null,
        self::OP_BODY_SWAP_SIZE => Parser::DEFAULT_BODY_SWAP_SIZE,
        self::OP_MAX_HEADER_BYTES => Parser::DEFAULT_MAX_HEADER_BYTES,
        self::OP_MAX_BODY_BYTES => Parser::DEFAULT_MAX_BODY_BYTES
    ];

    public function __construct(CookieJar $cookieJar = null, HttpSocketPool $socketPool = null, WriterFactory $writerFactory = null) {
        $this->cookieJar = $cookieJar ?: new ArrayCookieJar;
        $this->socketPool = $socketPool ?: new HttpSocketPool();
        $this->writerFactory = $writerFactory ?: new WriterFactory;
        $this->hasZlib = extension_loaded('zlib');
        $this->dequeuer = function() {
            if ($this->queue) {
                $this->dequeueNextRequest();
            }
        };

        /*
        \Amp\repeat(function() {
            printf("outstanding requests: %s\n", self::$outstandingRequests);
        }, 1000);
        */
    }

    /**
     * Asynchronously request multiple HTTP resources
     *
     * Note that this method is simply a convenience as storing the results of multiple calls to
     * Client::request() in an array will achieve the same effect as Client::requestMulti().
     *
     * @param array $urisAndRequests An array of URI strings and/or Request instances
     * @param array $options An array specifying options applicable only for this request batch
     * @return array[\Amp\Promise] An array of promises whose keys match the request array
     */
    public function requestMulti(array $urisAndRequests, array $options = []) {
        $promises = [];
        foreach ($urisAndRequests as $key => $request) {
            $promises[$key] = $this->request($request, $options);
        }

        return $promises;
    }

    /**
     * Asynchronously request an HTTP resource
     *
     * @param mixed[string|\Amp\Artax\Request] An HTTP URI string or an \Amp\Artax\Request instance
     * @param array $options An array specifying options applicable only for this request
     * @return \Amp\Promise A promise to resolve the request at some point in the future
     */
    public function request($uriOrRequest, array $options = []) {
        $promisor = new Deferred;

        try {
            $cycle = new RequestCycle;
            $cycle->futureResponse = $promisor;
            list($cycle->request, $cycle->uri) = $this->generateRequestFromUri($uriOrRequest);
            $cycle->options = $options ? array_merge($this->options, $options) : $this->options;
            $body = $cycle->request->getBody();
            if ($body instanceof AggregateBody) {
                $this->processAggregateBody($cycle, $body);
            } else {
                $this->finalizeRequest($cycle);
            }
        } catch (\Exception $e) {
            $promisor->fail($e);
        }

        return $promisor->promise();
    }

    private function dequeueNextRequest() {
        $cycle = array_shift($this->queue);
        $authority = $this->generateAuthorityFromUri($cycle->uri);
        $socketCheckoutUri = $cycle->uri->getScheme() . "://{$authority}";
        $cycle->socketCheckoutUri = $socketCheckoutUri;
        $futureSocket = $this->socketPool->checkout($socketCheckoutUri, $cycle->options);
        $futureSocket->when(function($error, $result) use ($cycle) {
            $this->onSocketResolve($cycle, $error, $result);
        });
    }

    private function processAggregateBody(RequestCycle $cycle, AggregateBody $body) {
        $promise = $body->getBody();
        $promise->when(function($error, $result) use ($cycle, $body) {
            if ($error) {
                $this->fail($cycle, $error);
            } else {
                $cycle->request->setBody($result);
                $this->processAggregateBodyHeaders($cycle, $body);
            }
        });
    }

    private function processAggregateBodyHeaders(RequestCycle $cycle, AggregateBody $body) {
        $promise = $body->getHeaders();
        $promise->when(function($error, $result) use ($cycle, $body) {
            if ($error) {
                $this->fail($cycle, $error);
            } else {
                $cycle->request->setAllHeaders($result);
                $this->finalizeRequest($cycle);
            }
        });
    }

    private function finalizeRequest(RequestCycle $cycle) {
        $uri = $cycle->uri;
        $options = $cycle->options;
        $promisor = $cycle->futureResponse;
        $request = $cycle->request;

        $this->normalizeRequestMethod($request);
        $this->normalizeRequestProtocol($request);
        $this->normalizeRequestBodyHeaders($request, $options, $promisor);
        $this->normalizeRequestEncodingHeaderForZlib($request, $options);
        $this->normalizeRequestHostHeader($request, $uri);
        $this->normalizeRequestUserAgent($request, $options);
        $this->normalizeRequestAcceptHeader($request);
        $this->assignApplicableRequestCookies($request, $options);

        $this->queue[] = $cycle;
        $promisor->promise()->when($this->dequeuer);

        if (count($this->queue) < 512) {
            $this->dequeueNextRequest();
        }
    }

    private function generateRequestFromUri($uriOrRequest) {
        if (is_string($uriOrRequest)) {
            $uri = $this->buildUriFromString($uriOrRequest);
            $request = new Request;
        } elseif ($uriOrRequest instanceof Request) {
            $uri = $this->buildUriFromString((string) $uriOrRequest->getUri());
            $request = $uriOrRequest;
        } else {
            throw new \InvalidArgumentException(
                'Request must be a valid HTTP URI or Amp\Artax\Request instance'
            );
        }

        $request->setUri($uri);

        return [$request, $uri];
    }

    private function buildUriFromString($str) {
        try {
            $uri = new Uri($str);
            $scheme = $uri->getScheme();

            if (($scheme === 'http' || $scheme === 'https') && $uri->getHost()) {
                return $uri;
            } else {
                throw new \InvalidArgumentException(
                    'Request must specify a valid HTTP URI'
                );
            }
        } catch (\DomainException $e) {
            throw new \InvalidArgumentException(
                $msg = 'Request must specify a valid HTTP URI',
                $code = 0,
                $prev = $e
            );
        }
    }

    private function normalizeRequestMethod(Request $request) {
        if (!$method = $request->getMethod()) {
            $request->setMethod('GET');
        }
    }

    private function normalizeRequestProtocol(Request $request) {
        if (!$protocol = $request->getProtocol()) {
            $request->setProtocol('1.1');
        } elseif (!($protocol == '1.0' || $protocol == '1.1')) {
            throw new \InvalidArgumentException(
                'Invalid request protocol: ' . $protocol
            );
        }
    }

    private function normalizeRequestBodyHeaders(Request $request, array $options) {
        if ($request->hasHeader('Content-Length')) {
            // If the user manually assigned a Content-Length we don't need to do anything here.
            return;
        }

        $body = $request->getBody();
        $method = $request->getMethod();

        if (empty($body) && $body !== '0' && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $request->setHeader('Content-Length', '0');
            $request->removeHeader('Transfer-Encoding');
        } elseif (is_scalar($body) && $body !== '') {
            $body = (string) $body;
            $request->setBody($body);
            $request->setHeader('Content-Length', strlen($body));
            $request->removeHeader('Transfer-Encoding');
        } elseif ($body instanceof \Iterator) {
            $request->removeHeader('Content-Length');
            $request->setHeader('Transfer-Encoding', 'chunked');
            $chunkedBody = new ChunkingIterator($body);
            $request->setBody($chunkedBody);
        } elseif ($body !== null) {
            throw new \InvalidArgumentException(
                'Request entity body must be a scalar or Iterator'
            );
        }

        if ($body && $options[self::OP_EXPECT_CONTINUE] && !$request->hasHeader('Expect')) {
            $request->setHeader('Expect', '100-continue');
        }

        if ($method === 'TRACE' || $method === 'HEAD' || $method === 'OPTIONS') {
            $request->setBody(null);
        }
    }

    private function normalizeRequestEncodingHeaderForZlib(Request $request, array $options) {
        $autoEncoding = $options[self::OP_AUTO_ENCODING];
        if ($autoEncoding && $this->hasZlib) {
            $request->setHeader('Accept-Encoding', 'gzip, identity');
        } elseif ($autoEncoding) {
            $request->removeHeader('Accept-Encoding');
        }
    }

    private function normalizeRequestHostHeader(Request $request, Uri $uri) {
        if ($request->hasHeader('Host')) {
            return;
        }

        $authority = $this->generateAuthorityFromUri($uri);
        $host = $this->removePortFromHost($authority);
        $request->setHeader('Host', $host);
    }

    private function removePortFromHost($host) {
        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80 or 443
        if (strpos($host, ':80') === strlen($host) - 3) {
            $host = substr($host, 0, -3);
        } else if (strpos($host, ':443') === strlen($host) - 4) {
            $host = substr($host, 0, -4);
        }

        return $host;
    }

    private function normalizeRequestUserAgent(Request $request, array $options) {
        if (!$request->hasHeader('User-Agent')) {
            $userAgent = $options[self::OP_DEFAULT_USER_AGENT] ?: self::USER_AGENT;
            $request->setHeader('User-Agent', $userAgent);
        }
    }

    private function normalizeRequestAcceptHeader(Request $request) {
        if (!$request->hasHeader('Accept')) {
            $request->setHeader('Accept', '*/*');
        }
    }

    private function assignApplicableRequestCookies($request, array $options) {
        $urlParts = parse_url($request->getUri());
        $domain = $urlParts['host'];
        $path = isset($urlParts['path']) ? $urlParts['path'] : '/';

        if (!$applicableCookies = $this->cookieJar->get($domain, $path)) {
            // No cookies matched our request; we're finished.
            return;
        }

        $isRequestSecure = strcasecmp($urlParts['scheme'], 'https') === 0;
        $cookiePairs = [];
        foreach ($applicableCookies as $cookie) {
            if (!$cookie->getSecure() || $isRequestSecure) {
                $cookiePairs[] = $cookie->getName() . '=' . $cookie->getValue();
            }
        }

        if ($cookiePairs) {
            $value = $options[self::OP_COMBINE_COOKIES] ? implode('; ', $cookiePairs) : $cookiePairs;
            $request->setHeader('Cookie', $value);
        }
    }

    private function generateAuthorityFromUri(Uri $uri) {
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $port = $uri->getPort();
        if (empty($port)) {
            $port = ($scheme === 'https') ? 443 : 80;
        }

        return "{$host}:{$port}";
    }

    private function onSocketResolve(RequestCycle $cycle, $error, $socket) {
        if ($error) {
            $this->fail($cycle, $error);
            return;
        }

        $cycle->socket = $socket;
        $cycle->socketProcuredAt = microtime(true);
        $cycle->futureResponse->update([Notify::SOCK_PROCURED, null]);

        if ($cycle->uri->getScheme() === 'https') {
            $this->enableCrypto($cycle);
        } else {
            $this->onCryptoCompletion($cycle);
        }
    }

    private function enableCrypto(RequestCycle $cycle) {
        $cryptoOptions = $this->generateCryptoOptions($cycle);
        $cryptoPromise = socket\cryptoEnable($cycle->socket, $cryptoOptions);
        $cryptoPromise->when(function($error) use ($cycle) {
            if ($error) {
                // If crypto failed we make sure the socket pool gets rid of its reference
                // to this socket connection.
                $this->socketPool->clear($cycle->socket);
                $this->fail($cycle, $error);
            } else {
                $this->onCryptoCompletion($cycle);
            }
        });
    }

    private function generateCryptoOptions(RequestCycle $cycle) {
        $options = ['peer_name' => $cycle->uri->getHost()];

        // Allow client-wide TLS settings
        if ($this->options[self::OP_CRYPTO]) {
            $options = array_merge($options, $this->options[self::OP_CRYPTO]);
        }

        // Allow per-request TLS settings
        if ($cycle->options[self::OP_CRYPTO]) {
            $options = array_merge($options, $cycle->options[self::OP_CRYPTO]);
        }

        return $options;
    }

    private function onCryptoCompletion(RequestCycle $cycle) {
        $parser = new Parser(Parser::MODE_RESPONSE);
        $parser->enqueueResponseMethodMatch($cycle->request->getMethod());
        $futureResponse = $cycle->futureResponse;
        $parser->setAllOptions([
            // @TODO Add support for non-blocking filesystem IO
            Parser::OP_DISCARD_BODY => $cycle->options[self::OP_DISCARD_BODY],
            Parser::OP_BODY_SWAP_SIZE => $cycle->options[self::OP_BODY_SWAP_SIZE],
            Parser::OP_MAX_HEADER_BYTES => $cycle->options[self::OP_MAX_HEADER_BYTES],
            Parser::OP_MAX_BODY_BYTES => $cycle->options[self::OP_MAX_BODY_BYTES],
            Parser::OP_RETURN_BEFORE_ENTITY => true,
            Parser::OP_BODY_DATA_CALLBACK => function($data) use ($futureResponse) {
                $futureResponse->update([Notify::RESPONSE_BODY_DATA, $data]);
            }
        ]);

        $cycle->parser = $parser;
        $cycle->readWatcher = \Amp\onReadable($cycle->socket, function() use ($cycle) {
            $this->onReadableSocket($cycle);
        });

        $timeout = $cycle->options[self::OP_MS_TRANSFER_TIMEOUT];
        if ($timeout > 0) {
            $cycle->transferTimeoutWatcher = \Amp\once(function() use ($cycle, $timeout) {
                $this->fail($cycle, new TimeoutException(
                    sprintf('Allowed transfer timeout exceeded: %d ms', $timeout)
                ));
            }, $timeout);
        }

        $this->writeRequest($cycle);
    }

    private function onReadableSocket(RequestCycle $cycle) {
        $socket = $cycle->socket;
        $data = @fread($socket, $cycle->options[self::OP_IO_GRANULARITY]);

        if ($data != '') {
            $this->consumeSocketData($cycle, $data);
        } elseif ($this->isSocketDead($socket)) {
            // Some HTTP messages are terminated by the closing of the connection.
            // Instead of blanket failing request cycles whose sockets disconnect
            // we need to determine if this was one of those HTTP messages.
            $this->processDeadSocket($cycle);
        }
    }

    private function consumeSocketData(RequestCycle $cycle, $data) {
        $cycle->lastDataRcvdAt = microtime(true);
        $cycle->bytesRcvd += strlen($data);
        if ($cycle->options[self::OP_VERBOSITY] & self::VERBOSE_READ) {
            echo $data;
        }
        $cycle->futureResponse->update([Notify::SOCK_DATA_IN, $data]);
        $cycle->parser->buffer($data);
        $this->parseSocketData($cycle);
    }

    private function parseSocketData(RequestCycle $cycle) {
        try {
            while ($parsedResponseArr = $cycle->parser->parse()) {
                if ($parsedResponseArr['headersOnly']) {
                    $data = [Notify::RESPONSE_HEADERS, $parsedResponseArr];
                    $cycle->futureResponse->update($data);
                    continue;
                } elseif (isset($cycle->continueWatcher) && ($parsedResponseArr['status'] == 100)) {
                    $this->proceedFrom100ContinueState($cycle);
                } else {
                    $this->assignParsedResponse($cycle, $parsedResponseArr);
                }

                if ($cycle->parser->getBuffer()) {
                    \Amp\immediately(function() use ($cycle) {
                        $this->parseSocketData($cycle);
                    });
                }

                break;
            }
        } catch (ParseException $e) {
            $this->fail($cycle, $e);
        }
    }

    private function assignParsedResponse(RequestCycle $cycle, array $parsedResponseArr) {
        $this->collectRequestCycleWatchers($cycle);

        if (($body = $parsedResponseArr['body']) && $cycle->options[self::OP_BUFFER_BODY]) {
            $body = stream_get_contents($body);
        }

        /**
         * @var $response \Amp\Artax\Response
         */
        $cycle->response = $response = (new Response)
            ->setStatus($parsedResponseArr['status'])
            ->setReason($parsedResponseArr['reason'])
            ->setProtocol($parsedResponseArr['protocol'])
            ->setBody($body)
            ->setAllHeaders($parsedResponseArr['headers'])
        ;

        if ($this->canDecompressResponseBody($response)) {
            $this->inflateGzipBody($response);
        }

        if ($response->hasHeader('Set-Cookie')) {
            $requestDomain = parse_url($cycle->request->getUri(), PHP_URL_HOST);
            $cookies = $response->getHeader('Set-Cookie');
            foreach ($cookies as $rawCookieStr) {
                $this->storeResponseCookie($requestDomain, $rawCookieStr);
            }
        }

        $response->setRequest(clone $cycle->request);

        if ($newUri = $this->getRedirectUri($cycle)) {
            return $this->redirect($cycle, $newUri);
        }

        if ($cycle->previousResponse) {
            $response->setPreviousResponse($cycle->previousResponse);
        }

        $socket = $cycle->socket;
        $cycle->futureResponse->update([Notify::RESPONSE, $cycle->response, "export_socket" => function () use (&$socket, $parsedResponseArr) {
            if ($socket) {
                $sock = $socket;
                $socket = null;
                $this->socketPool->clear($sock);
                return [$sock, $parsedResponseArr['buffer']];
            }
            throw new \LogicException("Cannot export socket after notification callback invocation");
        }]);

        // Do socket check-in *after* redirects because we handle the socket
        // differently if we need to redirect.
        if ($socket) {
            if ($this->shouldCloseSocketAfterResponse($cycle->request, $cycle->response)) {
                @fclose($socket);
                $this->socketPool->clear($socket);
            } else {
                $this->socketPool->checkin($socket);
            }
            $socket = null;
        }

        // If we've held any sockets over from previous redirected requests
        // we need to check those back in now.
        if ($cycle->redirectedSockets) {
            foreach ($cycle->redirectedSockets as $socket) {
                $this->socketPool->checkin($socket);
            }
        }

        $cycle->futureResponse->succeed($response);
    }

    private function collectRequestCycleWatchers(RequestCycle $cycle) {
        if (isset($cycle->readWatcher)) {
            \Amp\cancel($cycle->readWatcher);
            $cycle->readWatcher = null;
        }
        if (isset($cycle->continueWatcher)) {
            \Amp\cancel($cycle->continueWatcher);
            $cycle->continueWatcher = null;
        }
        if (isset($cycle->transferTimeoutWatcher)) {
            \Amp\cancel($cycle->transferTimeoutWatcher);
            $cycle->transferTimeoutWatcher = null;
        }
    }

    private function shouldCloseSocketAfterResponse(Request $request, Response $response) {
        $requestConnHeader = $request->hasHeader('Connection')
            ? current($request->getHeader('Connection'))
            : null;

        $responseConnHeader = $response->hasHeader('Connection')
            ? current($response->getHeader('Connection'))
            : null;

        if ($requestConnHeader && !strcasecmp($requestConnHeader, 'close')) {
            return true;
        } elseif ($responseConnHeader && !strcasecmp($responseConnHeader, 'close')) {
            return true;
        } elseif ($response->getProtocol() == '1.0' && !$responseConnHeader) {
            return true;
        } else {
            return false;
        }
    }

    private function canDecompressResponseBody(Response $response) {
        if (!$this->hasZlib) {
            return false;
        }
        if (!$response->hasHeader('Content-Encoding')) {
            return false;
        }

        $contentEncodingHeader = trim(current($response->getHeader('Content-Encoding')));

        return (strcasecmp($contentEncodingHeader, 'gzip') === 0);
    }

    private function inflateGzipBody(Response $response) {
        $src = $response->getBody();

        if (is_resource($src)) {
            $destination = fopen('php://memory', 'r+');
            fseek($src, 10, SEEK_SET);
            stream_filter_prepend($src, 'zlib.inflate', STREAM_FILTER_READ);
            stream_copy_to_stream($src, $destination);
            rewind($destination);
            $response->setBody($destination);
        } elseif (strlen($src)) {
            $body = gzdecode($src);
            $response->setBody($body);
        }
    }

    private function storeResponseCookie($requestDomain, $rawCookieStr) {
        try {
            $cookie = CookieParser::parse($rawCookieStr);
            if (!$cookie->getDomain()) {
                $cookie = new Cookie(
                    $cookie->getName(),
                    $cookie->getValue(),
                    $cookie->getExpirationTime(),
                    $cookie->getPath(),
                    $requestDomain,
                    $cookie->getSecure(),
                    $cookie->getHttpOnly()
                );
            }
            $this->cookieJar->store($cookie);
        } catch (\InvalidArgumentException $e) {
            // Ignore malformed Set-Cookie headers
        }
    }

    private function getRedirectUri(RequestCycle $cycle) {
        $request = $cycle->request;
        $response = $cycle->response;

        if (!$cycle->options[self::OP_FOLLOW_LOCATION]) {
            return null;
        }

        if (!$response->hasHeader('Location')) {
            return null;
        }

        $status = $response->getStatus();
        $method = $request->getMethod();

        if ($status < 300 || $status > 399 || $method === 'HEAD') {
            return null;
        }

        $requestUri = new Uri($request->getUri());
        $redirectLocation = current($response->getHeader('Location'));

        if (!$requestUri->canResolve($redirectLocation)) {
            return null;
        }

        $newUri = $requestUri->resolve($redirectLocation);
        $cycle->redirectHistory[] = $request->getUri();

        return $newUri;
    }

    private function redirect(RequestCycle $cycle, Uri $newUri) {
        if (in_array($newUri->__toString(), $cycle->redirectHistory)) {
            $this->fail($cycle, new InfiniteRedirectException(
                sprintf('Infinite redirect detected while following Location header: %s', $newUri)
            ));
            return;
        }

        $request = $cycle->request;
        $response = $cycle->response;
        $cycle->previousResponse = clone $response;
        $cycle->response = null;

        $refererUri = $request->getUri();

        $cycle->uri = $newUri;
        $authority = $this->generateAuthorityFromUri($newUri);
        $socketCheckoutUri = $newUri->getScheme() . "://{$authority}";
        $request->setUri($newUri->__toString());
        $host = $this->removePortFromHost($authority);
        $request->setHeader('Host', $host);
        $this->assignApplicableRequestCookies($request, $cycle->options);

        /**
         * If this is a 302/303 we need to follow the location with a GET if the
         * original request wasn't GET/HEAD. Otherwise, be sure to rewind the body
         * if it's an Iterator instance because we'll need to send it again.
         *
         * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html#sec10.3.3
         */
        $method = $request->getMethod();
        $status = $response->getStatus();
        if (($status == 302 || $status == 303) && !($method == 'GET' || $method == 'HEAD')) {
            $request->setMethod('GET');
            $request->removeHeader('Transfer-Encoding');
            $request->removeHeader('Content-Length');
            $request->removeHeader('Content-Type');
            $request->setBody(null);
        } elseif (($body = $request->getBody()) && $body instanceof \Iterator) {
            $body->rewind();
        }

        if ($cycle->options[self::OP_AUTO_REFERER]) {
            $this->assignRedirectRefererHeader($refererUri, $newUri, $request);
        }

        $cycle->futureResponse->update([Notify::REDIRECT, $refererUri, (string)$newUri]);

        if ($this->shouldCloseSocketAfterResponse($request, $response)) {
            // If the HTTP response dictated a connection close we need
            // to inform the socket pool and checkout a new connection.
            @fclose($cycle->socket);
            $this->socketPool->clear($cycle->socket);
            $cycle->socket = null;
            $cycle->socketCheckoutUri = $socketCheckoutUri;
            $futureSocket = $this->socketPool->checkout($socketCheckoutUri, $cycle->options);
            $futureSocket->when(function($error, $result) use ($cycle) {
                $this->onSocketResolve($cycle, $error, $result);
            });
        } elseif ($cycle->socketCheckoutUri === $socketCheckoutUri) {
            // Woot! We can reuse the socket we already have \o/
            $this->onSocketResolve($cycle, $error = null, $cycle->socket);
        } else {
            // Store the original socket and don't check it back into the
            // pool until we are finished with all redirects. This helps
            // prevent redirect checkout requests from being queued and
            // causing unwanted waiting to complete redirects than need to
            // traverse multiple host names.
            $cycle->socketCheckoutUri = $socketCheckoutUri;
            $cycle->redirectedSockets[] = $cycle->socket;
            $cycle->socket = null;
            $futureSocket = $this->socketPool->checkout($socketCheckoutUri, $cycle->options);
            $futureSocket->when(function($error, $result) use ($cycle) {
                $this->onSocketResolve($cycle, $error, $result);
            });
        }
    }

    /**
     * Clients must not add a Referer header when leaving an unencrypted resource
     * and redirecting to an encrypted resource.
     *
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec15.html#sec15.1.3
     */
    private function assignRedirectRefererHeader($refererUri, $newUri, $request) {
        if (!$refererIsEncrypted = (stripos($refererUri, 'https') === 0)) {
            $request->setHeader('Referer', $refererUri);
        } elseif ($destinationIsEncrypted = (stripos($newUri, 'https') === 0)) {
            $request->setHeader('Referer', $refererUri);
        } else {
            $request->removeHeader('Referer');
        }
    }

    private function isSocketDead($socketResource) {
        return (!is_resource($socketResource) || @feof($socketResource));
    }

    private function processDeadSocket(RequestCycle $cycle) {
        $parserState = $cycle->parser->getState();
        if ($parserState == Parser::BODY_IDENTITY_EOF) {
            $parsedResponseArr = $cycle->parser->getParsedMessageArray();
            $this->assignParsedResponse($cycle, $parsedResponseArr);
        } elseif ($parserState == Parser::AWAITING_HEADERS && empty($cycle->retryCount)) {
            $this->retry($cycle);
        } else {
            $this->fail($cycle, new SocketException(
                sprintf(
                    'Socket disconnected prior to response completion (Parser state: %s)',
                    $parserState
                )
            ));
        }
    }

    private function retry(RequestCycle $cycle) {
        $cycle->retryCount++;
        $this->collectRequestCycleWatchers($cycle);
        $this->socketPool->clear($cycle->socket);
        array_unshift($this->queue, $cycle);
        $this->dequeueNextRequest();
    }

    private function writeRequest(RequestCycle $cycle) {
        $rawHeaders = $this->generateRawRequestHeaders($cycle->request);
        $writePromise = (new BufferWriter)->write($cycle->socket, $rawHeaders);
        $writePromise->watch(function($update) use ($cycle) {
            $cycle->futureResponse->update([Notify::SOCK_DATA_OUT, $update]);
            if ($cycle->options[self::OP_VERBOSITY] & self::VERBOSE_SEND) {
                echo $update;
            }
        });
        $writePromise->when(function($error, $response) use ($cycle) {
            if ($error) {
                $this->fail($cycle, $error);
            } else {
                $this->afterHeaderWrite($cycle);
            }
        });
    }

    /**
     * @TODO Send absolute URIs in the request line when using a proxy server
     *       Right now this doesn't matter because all proxy requests use a CONNECT
     *       tunnel but this likely will not always be the case.
     */
    private function generateRawRequestHeaders(Request $request) {
        $uri = $request->getUri();
        $uri = new Uri($uri);

        $requestUri = $uri->getPath() ?: '/';

        if ($query = $uri->getQuery()) {
            $requestUri .= '?' . $query;
        }

        $str = $request->getMethod() . ' ' . $requestUri . ' HTTP/' . $request->getProtocol() . "\r\n";

        foreach ($request->getAllHeaders() as $field => $valueArr) {
            foreach ($valueArr as $value) {
                $str .= "{$field}: {$value}\r\n";
            }
        }

        $str .= "\r\n";

        return $str;
    }

    private function afterHeaderWrite(RequestCycle $cycle) {
        $body = $cycle->request->getBody();

        if ($body == '') {
            // We're finished if there's no body in the request.
            $cycle->futureResponse->update([Notify::REQUEST_SENT, $cycle->request]);
        } elseif ($this->requestExpects100Continue($cycle->request)) {
            $cycle->continueWatcher = \Amp\once(function() use ($cycle) {
                $this->proceedFrom100ContinueState($cycle);
            }, $cycle->options[self::OP_MS_100_CONTINUE_TIMEOUT]);
        } else {
            $this->writeBody($cycle, $body);
        }
    }

    private function requestExpects100Continue(Request $request) {
        if (!$request->hasHeader('Expect')) {
            return false;
        } elseif (stripos(implode(',', $request->getHeader('Expect')), '100-continue') !== false) {
            return true;
        } else {
            return false;
        }
    }

    private function proceedFrom100ContinueState(RequestCycle $cycle) {
        $continueWatcher = $cycle->continueWatcher;
        $cycle->continueWatcher = null;
        \Amp\cancel($continueWatcher);
        $this->writeBody($cycle);
    }

    private function writeBody(RequestCycle $cycle, $body) {
        try {
            $writer = $this->writerFactory->make($body);
            $writePromise = $writer->write($cycle->socket, $body);
            $writePromise->watch(function($update) use ($cycle) {
                $cycle->futureResponse->update([Notify::SOCK_DATA_OUT, $update]);
                if ($cycle->options[self::OP_VERBOSITY] & self::VERBOSE_SEND) {
                    echo $update;
                }
            });
            $writePromise->when(function($error, $result) use ($cycle) {
                if ($error) {
                    $this->fail($cycle, $error);
                } else {
                    $cycle->futureResponse->update([Notify::REQUEST_SENT, $cycle->request]);
                }
            });
        } catch (\Exception $e) {
            $this->fail($cycle, $e);
        }
    }

    private function fail(RequestCycle $cycle, $error) {
        $this->collectRequestCycleWatchers($cycle);

        if ($cycle->socket) {
            $this->socketPool->clear($cycle->socket);
        }

        $cycle->futureResponse->update([Notify::ERROR, $error]);
        $cycle->futureResponse->fail($error);
    }

    /**
     * Set multiple Client options at once
     *
     * @param array $options An array of the form [OP_CONSTANT => $value]
     * @throws \DomainException on Unknown option key
     * @return self
     */
    public function setAllOptions(array $options) {
        foreach ($options as $option => $value) {
            $this->setOption($option, $value);
        }

        return $this;
    }

    /**
     * Set an individual Client option
     *
     * @param int $option A Client option constant
     * @param mixed $value The option value to assign
     * @throws \DomainException On unknown option key
     * @return self
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_AUTO_ENCODING:
                $this->options[self::OP_AUTO_ENCODING] = (bool) $value;
                break;
            case self::OP_HOST_CONNECTION_LIMIT:
                $this->options[self::OP_HOST_CONNECTION_LIMIT] = $value;
                break;
            case self::OP_MS_CONNECT_TIMEOUT:
                $this->options[self::OP_MS_CONNECT_TIMEOUT] = $value;
                break;
            case self::OP_MS_KEEP_ALIVE_TIMEOUT:
                $this->options[self::OP_MS_KEEP_ALIVE_TIMEOUT] = $value;
                break;
            case self::OP_MS_TRANSFER_TIMEOUT:
                $this->options[self::OP_MS_TRANSFER_TIMEOUT] = (int) $value;
                break;
            case self::OP_MS_100_CONTINUE_TIMEOUT:
                $this->options[self::OP_MS_100_CONTINUE_TIMEOUT] = (int) $value;
                break;
            case self::OP_EXPECT_CONTINUE:
                $this->options[self::OP_EXPECT_CONTINUE] = (bool) $value;
                break;
            case self::OP_FOLLOW_LOCATION:
                $this->options[self::OP_FOLLOW_LOCATION] = (bool) $value;
                break;
            case self::OP_AUTO_REFERER:
                $this->options[self::OP_AUTO_REFERER] = (bool) $value;
                break;
            case self::OP_BUFFER_BODY:
                $this->options[self::OP_BUFFER_BODY] = (bool) $value;
                break;
            case self::OP_DISCARD_BODY:
                $this->options[self::OP_DISCARD_BODY] = (bool) $value;
                break;
            case self::OP_VERBOSITY:
                $this->options[self::OP_VERBOSITY] = (int) $value;
                break;
            case self::OP_IO_GRANULARITY:
                $value = (int) $value;
                $this->options[self::OP_IO_GRANULARITY] = $value > 0 ? $value : 32768;
                break;
            case self::OP_BINDTO:
                $this->options[self::OP_BINDTO] = $value;
                break;
            case self::OP_COMBINE_COOKIES:
                $this->options[self::OP_COMBINE_COOKIES] = (bool) $value;
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
            case self::OP_BODY_SWAP_SIZE:
                $this->options[self::OP_BODY_SWAP_SIZE] = (int) $value;
                break;
            case self::OP_MAX_HEADER_BYTES:
                $this->options[self::OP_MAX_HEADER_BYTES] = (int) $value;
                break;
            case self::OP_MAX_BODY_BYTES:
                $this->options[self::OP_MAX_BODY_BYTES] = (int) $value;
                break;
            default:
                throw new \DomainException(
                    sprintf("Unknown option: %s", $option)
                );
        }

        return $this;
    }
}
