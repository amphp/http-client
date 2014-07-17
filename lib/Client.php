<?php

namespace Artax;

use Alert\Reactor,
    After\Deferred,
    After\DeferredLock,
    Artax\Cookie\Cookie,
    Artax\Cookie\CookieJar,
    Artax\Cookie\ArrayCookieJar,
    Artax\Cookie\CookieParser;

class Client {
    const USER_AGENT = 'Artax/0.8.0-dev (PHP5.4+)';

    const OP_AUTO_ENCODING = 1;
    const OP_USE_KEEP_ALIVE = 2;
    const OP_HOST_CONNECTION_LIMIT = 3;
    const OP_QUEUED_SOCKET_LIMIT = 4;
    const OP_MS_CONNECT_TIMEOUT = 5;
    const OP_MS_KEEP_ALIVE_TIMEOUT = 6;
    const OP_MS_TRANSFER_TIMEOUT = 7;
    const OP_MS_100_CONTINUE_TIMEOUT = 8;
    const OP_EXPECT_CONTINUE = 9;
    const OP_FOLLOW_LOCATION = 10;
    const OP_AUTO_REFERER = 11;
    const OP_BUFFER_BODY = 12;
    const OP_DISCARD_BODY = 13;
    const OP_VERBOSE = 14;
    const OP_IO_GRANULARITY = 15;
    const OP_BIND_IP_ADDRESS = 16;
    const OP_COMBINE_COOKIES = 17;
    const OP_USER_AGENT = 18;
    const OP_MS_DNS_TIMEOUT = 19;

    const VERBOSE_NONE = 0b00;
    const VERBOSE_SEND = 0b01;
    const VERBOSE_READ = 0b10;
    const VERBOSE_ALL  = 0b11;

    private $reactor;
    private $cookieJar;
    private $socketPool;
    private $hasZlib;
    private $opAutoEncoding = true;
    private $opUseKeepAlive = true;
    private $opMsTransferTimeout = 120000;
    private $opMs100ContinueTimeout = 3000;
    private $opExpectContinue = false;
    private $opFollowLocation = true;
    private $opAutoReferer = true;
    private $opBufferBody = true;
    private $opDiscardBody = false;
    private $opIoGranularity = 32768;
    private $opVerbose = self::VERBOSE_NONE;
    private $opCombineCookies = true;
    private $opUserAgent = self::USER_AGENT;

    public function __construct(Reactor $reactor, CookieJar $cookieJar = null, SocketPool $socketPool = null) {
        $this->reactor = $reactor;
        $this->cookieJar = $cookieJar ?: new ArrayCookieJar;
        $this->socketPool = $socketPool ?: new SocketPool($reactor);
        $this->hasZlib = extension_loaded('zlib');
    }

    /**
     * Make an asynchronous HTTP request
     *
     * @param string|Artax\Request An HTTP URI string or Artax\Request instance
     * @return After\Promise A placeholder value that resolves when the response completes or fails
     */
    public function request($uriOrRequest) {
        try {
            $cycle = new RequestCycle;
            $cycle->deferredResponse = new DeferredLock;
            $cycle->request = $request = $this->normalizeRequest($uriOrRequest);
            $authority = $this->generateAuthorityFromUri($request->getUri());
            $deferredSocket = $this->socketPool->checkout($authority);
            $deferredSocket->onResolve(function($error, $result) use ($cycle) {
                $this->onSocketResolve($cycle, $error, $result);
            });
        } catch (\Exception $e) {
            $cycle->deferredResponse->fail($e);
        }

        return $cycle->deferredResponse->promise();
    }

    private function normalizeRequest($uriOrRequest) {
        $request = $this->generateRequestFromUri($uriOrRequest);

        $this->normalizeRequestMethod($request);
        $this->normalizeRequestUserAgent($request);
        $this->normalizeRequestProtocol($request);
        $this->normalizeRequestBodyHeaders($request);
        $this->normalizeRequestKeepAliveHeader($request);
        $this->normalizeRequestEncodingHeaderForZlib($request);
        $this->normalizeRequestHostHeader($request);
        $this->assignApplicableRequestCookies($request);

        return $request;
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
                'Request must be a valid HTTP URI or Artax\Request instance'
            );
        }

        $request->setUri($uri);

        return $request;
    }

    private function buildUriFromString($str) {
        try {
            $uri = new Uri($str);
            $scheme = $uri->getScheme();

            // @TODO Remove this once SSL/TLS is fixed!
            if ($scheme === 'https') {
                throw new \Exception(
                    'Sorry! SSL/TLS isn\'t re-implemented yet :( working on it ...'
                );
            }

            if (($scheme === 'http' || $scheme === 'https') && $uri->getHost()) {
                return (string) $uri;
            } else {
                throw new \InvalidArgumentException(
                    $msg = 'Request must specify a valid HTTP URI'
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

    private function normalizeRequestUserAgent(Request $request) {
        if (!$request->hasHeader('User-Agent')) {
            $request->setHeader('User-Agent', $this->opUserAgent);
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

    private function normalizeRequestBodyHeaders(Request $request) {
        $body = $request->getBody();
        $method = $request->getMethod();

        if ($body instanceof AggregateBody) {
            $body = $this->normalizeRequestAggregate($request);
        }

        if (empty($body) && $body !== '0' && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $request->setHeader('Content-Length', '0');
            $request->removeHeader('Transfer-Encoding');
        } elseif (is_scalar($body) && $body !== '') {
            $body = (string) $body;
            $request->setBody($body);
            $request->setHeader('Content-Length', strlen($body));
            $request->removeHeader('Transfer-Encoding');
        } elseif ($body instanceof \Iterator) {
            $this->normalizeIteratorBodyRequest($request);
        } elseif ($body !== null) {
            throw new \InvalidArgumentException(
                'Request entity body must be a scalar or Iterator'
            );
        }

        if ($body && $this->opExpectContinue && !$request->hasHeader('Expect')) {
            $request->setHeader('Expect', '100-continue');
        }

        if ($method === 'TRACE' || $method === 'HEAD' || $method === 'OPTIONS') {
            $request->setBody(null);
        }
    }

    private function normalizeRequestAggregate(Request $request) {
        $body = $request->getBody();
        $request->setAllHeaders($body->getHeaders());
        $aggregatedBody = $body->getBody();
        $request->setBody($aggregatedBody);

        return $aggregatedBody;
    }

    private function normalizeIteratorBodyRequest(Request $request) {
        $body = $request->getBody();

        if ($body instanceof \Countable) {
            $request->setHeader('Content-Length', $body->count());
            $request->removeHeader('Transfer-Encoding');
        } elseif ($request->getProtocol() >= 1.1) {
            $request->removeHeader('Content-Length');
            $request->setHeader('Transfer-Encoding', 'chunked');
            $chunkedBody = new ChunkingIterator($body);
            $request->setBody($chunkedBody);
        } else {
            $resourceBody = $this->bufferIteratorResource($body);
            $request->setHeader('Content-Length', $resourceBody->count());
            $request->setBody($resourceBody);
        }
    }

    private function normalizeRequestKeepAliveHeader(Request $request) {
        if (!$this->opUseKeepAlive) {
            $request->setHeader('Connection', 'close');
        }
    }

    private function normalizeRequestEncodingHeaderForZlib(Request $request) {
        if ($this->opAutoEncoding && $this->hasZlib) {
            $request->setHeader('Accept-Encoding', 'gzip, identity');
        } elseif ($this->opAutoEncoding) {
            $request->removeHeader('Accept-Encoding');
        }
    }

    private function normalizeRequestHostHeader(Request $request) {
        $authority = $this->generateAuthorityFromUri($request->getUri());

        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80/443 port.
        if (stripos($authority, ':80') || stripos($authority, ':443')) {
            $authority = parse_url($authority, PHP_URL_HOST);
        }

        $request->setHeader('Host', $authority);
    }

    private function assignApplicableRequestCookies($request) {
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
            $value = $this->opCombineCookies ? implode('; ', $cookiePairs) : $cookiePairs;
            $request->setHeader('Cookie', $value);
        }
    }

    private function generateAuthorityFromUri($uri) {
        $uriParts = parse_url($uri);
        $host = $uriParts['host'];
        $needsEncryption = (strtolower($uriParts['scheme']) === 'https');

        if (empty($uriParts['port'])) {
            $port = $needsEncryption ? 443 : 80;
        } else {
            $port = $uriParts['port'];
        }

        return $host . ':' . $port;
    }

    private function onSocketResolve(RequestCycle $cycle, $error, $result) {
        if ($error) {
            $this->fail($cycle, $error);
            return;
        }

        $socket = $result;
        $parser = new Parser(Parser::MODE_RESPONSE);
        $parser->enqueueResponseMethodMatch($cycle->request->getMethod());
        $parser->setAllOptions([
            /*
            // @TODO Determine if we actually care about these in a client context
            // @TODO Expose a Client::OP_BODY_SWAP_SIZE option
            Parser::OP_MAX_HEADER_BYTES => $this->maxHeaderBytes,
            Parser::OP_MAX_BODY_BYTES => $this->maxBodyBytes,
            Parser::OP_BODY_SWAP_SIZE => $this->bodySwapSize,
            */
            Parser::OP_DISCARD_BODY => $this->opDiscardBody,
            Parser::OP_RETURN_BEFORE_ENTITY => true,
            Parser::OP_BODY_DATA_CALLBACK => function($data) use ($cycle) {
                $cycle->deferredResponse->progress([Notify::RESPONSE_BODY_DATA, $data]);
            }
        ]);

        $cycle->deferredResponse->progress([Notify::SOCK_PROCURED, null]);
        $cycle->socketProcuredAt = microtime(true);
        $cycle->socket = $socket;
        $cycle->parser = $parser;
        $cycle->readWatcher = $this->reactor->onReadable($socket, function() use ($cycle) {
            $this->onReadableSocket($cycle);
        });

        if ($this->opMsTransferTimeout > 0) {
                $cycle->transferTimeoutWatcher = $this->reactor->once(function() use ($cycle) {
                $this->fail($cycle, new TimeoutException(
                    sprintf('Allowed transfer timeout exceeded: %d ms', $this->opMsTransferTimeout)
                ));
            }, $this->opMsTransferTimeout);
        }

        $this->writeRequest($cycle);
    }

    private function onReadableSocket(RequestCycle $cycle) {
        $socket = $cycle->socket;
        $data = @fread($socket, $this->opIoGranularity);

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
        if ($this->opVerbose & self::VERBOSE_READ) {
            echo $data;
        }
        $cycle->deferredResponse->progress([Notify::SOCK_DATA_IN, $data]);
        $cycle->parser->buffer($data);
        $this->parseSocketData($cycle, $data);
    }

    private function parseSocketData(RequestCycle $cycle, $data) {
        try {
            while ($parsedResponseArr = $cycle->parser->parse()) {
                if ($parsedResponseArr['headersOnly']) {
                    $data = [Notify::RESPONSE_HEADERS, $parsedResponseArr['headers']];
                    $cycle->deferredResponse->progress($data);
                    continue;
                } elseif (isset($cycle->continueWatcher) && ($parsedResponseArr['status'] == 100)) {
                    $this->proceedFrom100ContinueState($cycle);
                } else {
                    $this->assignParsedResponse($cycle, $parsedResponseArr);
                }
            }
        } catch (ParseException $e) {
            $this->fail($cycle, $e);
        }
    }

    private function assignParsedResponse(RequestCycle $cycle, array $parsedResponseArr) {
        $this->collectRequestCycleWatchers($cycle);

        if (($body = $parsedResponseArr['body']) && $this->opBufferBody) {
            $body = stream_get_contents($body);
        }

        $cycle->response = $response = (new Response)
            ->setStatus($parsedResponseArr['status'])
            ->setReason($parsedResponseArr['reason'])
            ->setProtocol($parsedResponseArr['protocol'])
            ->setBody($body)
            ->setAllHeaders($parsedResponseArr['headers'])
        ;

        if ($this->shouldCloseSocketAfterResponse($cycle)) {
            @fclose($cycle->socket);
            $this->socketPool->clear($cycle->socket);
        } else {
            $this->socketPool->checkin($cycle->socket);
        }

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

        if ($newUri = $this->getRedirectUri($cycle)) {
            $this->redirect($cycle, $newUri);
        } else {
            $cycle->deferredResponse->progress([Notify::RESPONSE, $cycle->response]);
            $cycle->deferredResponse->succeed($response);
        }
    }

    private function collectRequestCycleWatchers(RequestCycle $cycle) {
        if (isset($cycle->readWatcher)) {
            $this->reactor->cancel($cycle->readWatcher);
            $cycle->readWatcher = null;
        }
        if (isset($cycle->writeWatcher)) {
            $this->reactor->cancel($cycle->writeWatcher);
            $cycle->writeWatcher = null;
        }
        if (isset($cycle->continueWatcher)) {
            $this->reactor->cancel($cycle->continueWatcher);
            $cycle->continueWatcher = null;
        }
        if (isset($cycle->transferTimeoutWatcher)) {
            $this->reactor->cancel($cycle->transferTimeoutWatcher);
            $cycle->transferTimeoutWatcher = null;
        }
    }

    private function shouldCloseSocketAfterResponse(RequestCycle $cycle) {
        $request = $cycle->request;
        $response = $cycle->response;

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
        } elseif (!$this->opUseKeepAlive) {
            return true;
        }

        return false;
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

        if (!($this->opFollowLocation && $response->hasHeader('Location'))) {
            return null;
        }

        $status = $response->getStatus();
        $method = $request->getMethod();

        if ($status < 200 || $status > 399 || !($method === 'GET' || $method === 'HEAD')) {
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
        $refererUri = $request->getUri();
        $cycle->response = null;
        $authority = $this->generateAuthorityFromUri($newUri);
        $request->setUri($newUri->__toString());
        $request->setHeader('Host', parse_url($authority, PHP_URL_HOST));
        $this->assignApplicableRequestCookies($request);

        if (($body = $request->getBody()) && $body instanceof \Iterator) {
            $body->rewind();
        }

        if ($this->opAutoReferer) {
            $this->assignRedirectRefererHeader($refererUri, $newUri, $request);
        }
        
        // @TODO Remove this once SSL/TLS is re-implemented!
        if ($newUri->getScheme() === 'https') {
            $cycle->deferredResponse->fail(new \Exception(
                'Sorry! SSL/TLS isn\'t re-implemented yet :( working on it ...'
            ));
            return;
        }

        $deferredSocket = $this->socketPool->checkout($authority);
        $deferredSocket->onResolve(function($error, $result) use ($cycle) {
            $this->onSocketResolve($cycle, $error, $result);
        });

        $cycle->deferredResponse->progress([Notify::REDIRECT, $refererUri, (string)$newUri]);
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
        if ($cycle->parser->getState() == Parser::BODY_IDENTITY_EOF) {
            $parsedResponseArr = $cycle->parser->getParsedMessageArray();
            $this->assignParsedResponse($cycle, $parsedResponseArr);
        } else {
            $this->fail($cycle, new SocketException(
                'Socket connection disconnected prior to response completion :('
            ));
        }
    }

    private function writeRequest(RequestCycle $cycle) {
        $cycle->writeBuffer = $this->generateRawRequestHeaders($cycle->request);
        $cycle->deferredWriteResult = new Deferred;
        $cycle->deferredWriteResult->onResolve(function($error, $result) use ($cycle) {
            if ($error) {
                $this->fail($cycle, $error);
            }
        });

        $this->write($cycle);
    }

    /**
     * @TODO Send absolute URIs in the request line when using a proxy server
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

    private function write(RequestCycle $cycle) {
        $bytesToWrite = strlen($cycle->writeBuffer);
        $bytesWritten = @fwrite($cycle->socket, $cycle->writeBuffer, $this->opIoGranularity);
        $cycle->bytesSent += $bytesWritten;

        $notifyData = substr($cycle->writeBuffer, 0, $bytesWritten);

        if ($bytesWritten) {
            $cycle->deferredResponse->progress([Notify::SOCK_DATA_OUT, $notifyData]);
        }

        if ($bytesWritten && $this->opVerbose & self::VERBOSE_SEND) {
            echo $notifyData;
        }

        if ($bytesToWrite === $bytesWritten) {
            $cycle->lastDataSentAt = microtime(true);
            $this->onWriteBufferDrain($cycle);
        } elseif ($bytesWritten > 0) {
            $cycle->lastDataSentAt = microtime(true);
            $this->onPartialDrain($cycle);
        } elseif ($this->isSocketDead($cycle->socket)) {
            $this->fail($cycle, new SocketException(
                'Socket disconnected prior to write completion :('
            ));
        } else {
            $this->onPartialDrain($cycle);
        }
    }

    private function onWriteBufferDrain(RequestCycle $cycle) {
        $cycle->writeBuffer = null;
        if ($cycle->isWritingBody) {
            $this->onWriteBufferBodyDrain($cycle);
        } else {
            $this->onWriteBufferHeaderDrain($cycle);
        }
    }

    private function onWriteBufferBodyDrain(RequestCycle $cycle) {
        if (!$cycle->requestBody instanceof \Iterator) {
            $this->finalizeSuccessfulWrite($cycle);
        } elseif ($cycle->requestBody->valid()) {
            $this->advanceIteratorBody($cycle);
            $this->write($cycle);
        } else {
            $this->finalizeSuccessfulWrite($cycle);
        }
    }

    private function finalizeSuccessfulWrite(RequestCycle $cycle) {
        if ($cycle->writeWatcher !== null) {
            $this->reactor->disable($cycle->writeWatcher);
        }
        $cycle->deferredResponse->progress([Notify::REQUEST_SENT, null]);
        $cycle->deferredWriteResult->succeed();
    }

    private function advanceIteratorBody(RequestCycle $cycle) {
        $body = $cycle->requestBody;
        $next = $body->current();
        $body->next();

        if ($next instanceof Promise) {
            $next->onResolve(function($error, $result) use ($cycle) {
                $this->onDeferredBodyElementResolution($cycle, $error, $result);
            });
        } elseif (is_string($next)) {
            $cycle->writeBuffer = $next;
        } else {
            $this->fail($cycle, new \DomainException(
                sprintf('Unexpected request body iterator element: %s', gettype($next))
            ));
        }
    }

    private function onDeferredBodyElementResolution(Cycle $cycle, $error, $result) {
        if ($error) {
            $this->fail($cycle, $error);
            return;
        }

        $next = $result;
        if (is_string($next)) {
            $cycle->writeBuffer = $next;
            $this->write($cycle);
        } else {
            $this->fail($cycle, new \DomainException(
                sprintf('Unexpected request body element resolved from future: %s', gettype($next))
            ));
        }
    }

    private function onWriteBufferHeaderDrain(RequestCycle $cycle) {
        $body = $cycle->requestBody = $cycle->request->getBody();
        if ($body == '') {
            // We're finished if there's no body in the request.
            $this->finalizeSuccessfulWrite($cycle);
        } elseif ($this->requestExpects100Continue($cycle->request)) {
        $cycle->continueWatcher = $this->reactor->once(function() use ($cycle) {
                $this->proceedFrom100ContinueState($cycle);
            }, $this->opMs100ContinueTimeout);
        } else {
            $this->initiateBodyWrite($cycle);
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
        $this->reactor->cancel($continueWatcher);
        $this->initiateBodyWrite($cycle);
    }

    private function initiateBodyWrite(RequestCycle $cycle) {
        $cycle->isWritingBody = true;
        $body = $cycle->requestBody;
        if (is_string($body)) {
            $cycle->writeBuffer = $body;
            $this->write($cycle);
        } elseif ($body instanceof \Iterator) {
            $this->advanceIteratorBody($cycle);
            $this->write($cycle);
        } else {
            $this->fail($cycle, new \DomainException(
                sprintf('Unexpected request body type: %s', gettype($body))
            ));
        }
    }

    private function onPartialDrain(RequestCycle $cycle) {
        // The explicit === null check is important as the writeWatcher may === 0
        if ($cycle->writeWatcher === null) {
            $socket = $writeWatcher->socket;
            $cycle->writeWatcher = $this->reactor->onWritable($socket, function() use ($cycle) {
                $this->write($cycle);
            });
        } else {
            $this->reactor->enable($cycle->writeWatcher);
        }
    }

    private function fail(RequestCycle $cycle, \Exception $error) {
        $this->collectRequestCycleWatchers($cycle);

        if ($cycle->socket) {
            $this->socketPool->clear($cycle->socket);
        }

        $cycle->deferredResponse->progress([Notify::ERROR, $error]);
        $cycle->deferredResponse->fail($error);
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
                $this->opAutoEncoding = (bool) $value;
                break;
            case self::OP_USE_KEEP_ALIVE:
                $this->opUseKeepAlive = (bool) $value;
                break;
            case self::OP_HOST_CONNECTION_LIMIT:
                $this->socketPool->setOption(SocketPool::OP_HOST_CONNECTION_LIMIT, $value);
                break;
            case self::OP_QUEUED_SOCKET_LIMIT:
                $this->socketPool->setOption(SocketPool::OP_QUEUED_SOCKET_LIMIT, $value);
                break;
            case self::OP_MS_CONNECT_TIMEOUT:
                $this->socketPool->setOption(SocketPool::OP_MS_CONNECT_TIMEOUT, $value);
                break;
            case self::OP_MS_KEEP_ALIVE_TIMEOUT:
                $this->socketPool->setOption(SocketPool::OP_MS_IDLE_TIMEOUT, $value);
                break;
            case self::OP_MS_TRANSFER_TIMEOUT:
                $this->opMsTransferTimeout = (int) $value;
                break;
            case self::OP_MS_100_CONTINUE_TIMEOUT:
                $this->opMs100ContinueTimeout = (int) $value;
                break;
            case self::OP_EXPECT_CONTINUE:
                $this->opExpectContinue = (bool) $value;
                break;
            case self::OP_FOLLOW_LOCATION:
                $this->opFollowLocation = (bool) $value;
                break;
            case self::OP_AUTO_REFERER:
                $this->opAutoReferer = (bool) $value;
                break;
            case self::OP_BUFFER_BODY:
                $this->opBufferBody = (bool) $value;
                break;
            case self::OP_DISCARD_BODY:
                $this->opDiscardBody = (bool) $value;
                break;
            case self::OP_VERBOSE:
                $this->opVerbose = (int) $value;
                break;
            case self::OP_IO_GRANULARITY:
                $value = (int) $value;
                $this->opIoGranularity = $value > 0 ? $value : 32768;
                break;
            case self::OP_BIND_IP_ADDRESS:
                $this->socketPool->setOption(SocketPool::OP_BIND_IP_ADDRESS, $value);
                break;
            case self::OP_COMBINE_COOKIES:
                $this->opCombineCookies = (bool) $value;
                break;
            case self::OP_USER_AGENT:
                $this->opUserAgent = (string) $value;
                break;
            case self::OP_MS_DNS_TIMEOUT:
                $this->socketPool->setOption(SocketPool::OP_MS_DNS_TIMEOUT, $value);
                break;
            default:
                throw new \DomainException(
                    sprintf("Unknown option: %s", $option)
                );
        }

        return $this;
    }
}
