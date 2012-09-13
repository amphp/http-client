<?php

namespace Artax;

use Exception,
    StdClass,
    Traversable,
    SplObjectStorage,
    Spl\Mediator,
    Spl\TypeException,
    Artax\Network\Stream,
    Artax\Network\SocketStream,
    Artax\Network\SslSocketStream,
    Artax\Network\NetworkException,
    Artax\Network\ConnectException,
    Artax\Http\Request,
    Artax\Http\StdRequest,
    Artax\Http\Response,
    Artax\Http\ChainableResponse;

class Client {
    
    /**
     * @var string
     */
    const USER_AGENT = 'Artax/0.1 (PHP5.3+)';
    
    /**
     * @var string
     */
    const EVENT_ERROR = 'artax.client.error';
    
    /**
     * @var string
     */
    const EVENT_REDIRECT = 'artax.client.redirect';
    
    /**
     * @var string
     */
    const EVENT_RESPONSE = 'artax.client.response';
    
    /**
     * @var string
     */
    const EVENT_STREAM_CHECKOUT = 'artax.client.conn.checkout';
    
    /**
     * @var string
     */
    const EVENT_STREAM_CHECKIN = 'artax.client.conn.checkin';
    
    /**
     * @var Spl\Mediator
     */
    private $mediator;
    
    /**
     * @var array
     */
    private $sockPool = array();
    
    /**
     * @var int
     */
    private $connectTimeout = 60;
    
    /**
     * @var int
     */
    private $hostConcurrencyLimit = 5;
    
    /**
     * @var int
     */
    private $maxRedirects = 10;
    
    /**
     * @var array
     */
    private $sslOptions = array();
    
    /**
     * @var bool
     */
    private $useProxyStyleRequests = false;
    
    /**
     * @var bool
     */
    private $allowNonStandardRedirects = false;
    
    /**
     * @var int
     */
    private $ioChunkSize = 8192;
    
    /**
     * @var SplObjectStorage
     */
    private $requestStateMap;
    
    /**
     * @param Spl\Mediator $mediator
     * @return void
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * Set the number of seconds to wait before a socket connection attempt times out.
     * 
     * @param int $secondsUntilTimeout
     * @return void
     */
    public function setConnectTimout($secondsUntilTimeout) {
        $this->connectTimeout = (int) $secondsUntilTimeout;
    }
    
    /**
     * Set custom SSL request options
     * 
     * To customize SSL connections, assign a key-value associative array of option values:
     * 
     *     $options = array(
     *         'verify_peer'       => true,
     *         'allow_self_signed' => true,
     *         'cafile'            => '/hard/path/to/cert/authority/file'
     *     );
     *     
     *     $client->setSslOptions($options);
     *  
     * 
     * A full list of available options may be viewed here:
     * http://www.php.net/manual/en/context.ssl.php
     * 
     * @param array $options
     * @return void
     */
    public function setSslOptions(array $options) {
        $this->sslOptions = $options;
    }
    
    /**
     * Set the maximum number of simultaneous connections allowed per host
     * 
     * The default value is 5. If the maximum number of simultaneous connections to a specific host
     * are already in use, further requests to that host are queued until one of the existing in-use
     * connections to that host becomes available.
     * 
     * @param int $maxConnections
     * @return void
     */
    public function setHostConcurrencyLimit($maxConnections) {
        $maxConnections = (int) $maxConnections;
        $maxConnections = $maxConnections < 1 ? 1 : $maxConnections;
        $this->hostConcurrencyLimit = $maxConnections;
    }
    
    /**
     * Set the maximum number of redirects allowed to fulfill a request. Defaults to 10.
     * 
     * Infinite redirection loops are detected immediately regardless of this value.
     * 
     * @param int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->maxRedirects = (int) $maxRedirects;
    }
    
    /**
     * Enable this option if your script must connect through a proxy server
     * 
     * @param bool $trueOrFalse
     * @return void
     */
    public function useProxyStyleRequests($trueOrFalse) {
        $this->useProxyStyleRequests = filter_var($trueOrFalse, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Should the client transparently redirect requests made using methods other than GET or HEAD?
     * 
     * According to RFC2616-10.3, "If the 301 status code is received in response to a request other
     * than GET or HEAD, the user agent MUST NOT automatically redirect the request unless it can be
     * confirmed by the user, since this might change the conditions under which the request was
     * issued."
     * 
     * This directive, if set to true, serves as confirmation that requests made using methods other
     * than GET/HEAD may be redirected automatically.
     * 
     * @param bool $trueOrFalse
     * @return void
     */
    public function allowNonStandardRedirects($trueOrFalse) {
        $this->allowNonStandardRedirects = filter_var($trueOrFalse, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Make an HTTP request
     * 
     * @param Artax\Http\Request $request
     * @return Artax\Http\ChainableResponse
     * @throws Artax\ClientException
     */
    public function send(Request $request) {
        $this->mapRequestStates(array($request));
        $this->doStreamSelect();
        
        $this->requestStateMap->rewind();
        $s = $this->requestStateMap->getInfo();
        
        if (!empty($s->error)) {
            throw new ClientException($s->error->getMessage(), null, $s->error);
        } else {
            return $s->response;
        }
    }
    
    /**
     * Make multiple HTTP requests in parallel
     * 
     * @param mixed $requests An array, StdClass or Traversable list of requests
     * @return Artax\Http\MultiResponse
     */
    public function sendMulti($requests) {
        $this->validateRequestTraversable($requests);
        $this->mapRequestStates($requests);
        $this->doStreamSelect();
        
        $responses = array();
        foreach ($this->requestStateMap as $request) {
            $s = $this->requestStateMap->getInfo();
            $responses[$s->key] = $s->error ?: $s->response;
        }
        
        return new MultiResponse($responses);
    }
    
    /**
     * @param mixed $requests An array, StdClass or Traversable object
     * @return void
     * @throws Spl\TypeException
     */
    protected function validateRequestTraversable($requests) {
        if (!(is_array($requests)
            || $requests instanceof Traversable
            || $requests instanceof StdClass
        )) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new TypeException(
                "Client::send expects an array, StdClass or Traversable object at Argument " .
                "1; $type provided"
            );
        }
        
        foreach ($requests as $request) {
            if (!$request instanceof Request) {
                $type = is_object($request) ? get_class($requests) : gettype($request);
                throw new TypeException(
                    "Client::sendMulti requires that each element of the list passed to Argument " .
                    "1 implement Artax\\Http\\Request; $type provided"
                );
            }
        }
    }
    
    /**
     * @param mixed $requests An array, StdClass or Traversable object
     * @return array
     */
    protected function mapRequestStates($requests) {
        $this->requestStateMap = new SplObjectStorage();
        $this->streamIdRequestMap = array();
        
        foreach ($requests as $key => $request) {
            $request = clone $request;
            $this->normalizeRequestHeaders($request);
            $s = $this->makeStateObj($key);
            $this->assignStreamToRequestState($request, $s);
            $this->requestStateMap->attach($request, $s);
        }
    }
    
    /**
     * @param string $key
     * @return StdClass
     */
    protected function makeStateObj($key) {
        $s = new StdClass;
        
        $s->key = $key;
        $s->stream = null;
        $s->error = null;
        $s->state = ClientState::AWAITING_SOCKET;
        $s->response = new ChainableResponse();
        $s->headerBytesSent = 0;
        $s->bodyBytesSent = 0;
        $s->bytesRecd = 0;
        $s->responseBuffer = '';
        $s->redirectHistory = array();
        
        return $s;
    }
    
    /**
     * @param Artax\Http\Request $request
     * @return void
     */
    protected function normalizeRequestHeaders(Request $request) {
        $request->setHeader('User-Agent', self::USER_AGENT);
        
        if ($this->useProxyStyleRequests) {
            $request->removeHeader('Host');
        } else {
            $request->setHeader('Host', $request->getAuthority());
        }
        
        if ($request->getBodyStream()) {
            $request->setHeader('Transfer-Encoding', 'chunked');
        } elseif ($request->allowsEntityBody() && $entityBody = $request->getBody()) {
            $request->setHeader('Content-Length', strlen($entityBody));
        } else {
            $request->removeHeader('Content-Length');
            $request->removeHeader('Transfer-Encoding');
        }
        
        $request->removeHeader('Accept-Encoding');
    }
    
    /**
     * @param Artax\Http\Request $request
     * @param StdClass $s
     * @return bool
     */
    protected function assignStreamToRequestState(Request $request, StdClass $s) {
        try {
            if ($stream = $this->checkoutStream($request)) {
                $s->stream = $stream;
                $s->state = ClientState::SENDING_REQUEST_HEADERS;
                $this->streamIdRequestMap[(int) $stream->getStream()] = $request;
                return true;
            }
        } catch (ConnectException $e) {
            $this->setErrorState($s, $e);
        }
        return false;
    }
    
    /**
     * @param StdClass $s
     * @param Exception $e
     * @return void
     */
    protected function setErrorState(StdClass $s, Exception $e) {
        $s->state = ClientState::ERROR;
        $s->error = $e;
        $this->mediator->notify(self::EVENT_ERROR, $s->key, $e);
    }
    
    /**
     * @return void
     */
    protected function doStreamSelect() {
        while (true) {
            if ($this->isFinished()) {
                return;
            }
            
            $ex = null;
            list($read, $write) = $this->getSelectableStreams();
            if (!($read || $write)) {
                continue;
            }
            
            if (!stream_select($read, $write, $ex, 3)) {
                continue;
            }
            
            foreach ($write as $streamKey => $stream) {
                $request = $this->streamIdRequestMap[$streamKey];
                $this->doSelectWrite($request);
            }
            
            foreach ($read as $streamKey => $stream) {
                $request = $this->streamIdRequestMap[$streamKey];
                $this->doSelectRead($request);
            }
        }
    }
    
    /**
     * @return array
     */
    protected function getSelectableStreams() {
        $read = array();
        $write = array();
        
        foreach ($this->requestStateMap as $request) {
            $s = $this->requestStateMap->getInfo();
            
            if ($s->state >= ClientState::RESPONSE_RECEIVED) {
                continue;
            } elseif ($s->state == ClientState::AWAITING_SOCKET) {
                if (!$this->assignStreamToRequestState($request, $s)) {
                    continue;
                }
            }
            
            $stream = $s->stream->getStream();
            $streamKey = (int) $stream;
            
            if ($s->state < ClientState::READING_HEADERS) {
                $write[$streamKey] = $stream;
            } elseif ($s->state < ClientState::RESPONSE_RECEIVED) {
                $read[$streamKey] = $s->stream->getStream();
            }
        }
        
        return array($read, $write);
    }
    
    /**
     * @param Artax\Http\Request $request
     */
    protected function doSelectWrite(Request $request) {
        try {
            $this->writeRequest($request);
        } catch (Exception $e) {
            $s = $this->requestStateMap->offsetGet($request);
            $this->setErrorState($s, $e);
        }
    }
    
    /**
     * @param Artax\Http\Request $request
     */
    protected function doSelectRead(Request $request) {
        try {
            $this->readResponse($request);
        } catch (Exception $e) {
            $s = $this->requestStateMap->offsetGet($request);
            $this->setErrorState($s, $e);
        }
    }
    
    /**
     * @return bool
     */
    protected function isFinished() {
        $completedCount = 0;
        foreach ($this->requestStateMap as $request) {
            $s = $this->requestStateMap->getInfo();
            $completedCount += $s->state >= ClientState::RESPONSE_RECEIVED;
        }
        return $completedCount == count($this->requestStateMap);
    }
    
    /**
     * @param Artax\Http\Request $request
     * @return Artax\Network\Stream Returns null if max concurrency limit already reached
     * @throws Artax\Network\ConnectException
     */
    protected function checkoutStream(Request $request) {
        $socketUri = $this->buildSocketUriFromRequest($request);
        $socketUriString = $socketUri->__toString();
        
        if (!isset($this->sockPool[$socketUriString])) {
            $this->sockPool[$socketUriString] = new SplObjectStorage();
        }
        
        foreach ($this->sockPool[$socketUriString] as $stream) {
            $isInUse = $this->sockPool[$socketUriString]->getInfo();
            if (!$isInUse) {
                $this->sockPool[$socketUriString]->setInfo(true);
                $this->mediator->notify(self::EVENT_STREAM_CHECKOUT, $stream);
                return $stream;
            }
        }
        
        $openHostStreams = count($this->sockPool[$socketUriString]);
        
        if ($this->hostConcurrencyLimit > $openHostStreams) {
            $stream = $this->makeStream($socketUri);
            $stream->setConnectTimeout($this->connectTimeout);
            $stream->connect();
            
            $this->sockPool[$socketUriString]->attach($stream, true);
            $this->mediator->notify(self::EVENT_STREAM_CHECKOUT, $stream);
            return $stream;
        } else {
            return null;
        }
    }
    
    /**
     * @param Artax\Http\Request $request
     * @return Artax\Uri
     */
    protected function buildSocketUriFromRequest(Request $request) {
        $requestScheme = strtolower($request->getScheme());
        if ($requestScheme == 'https') {
            $scheme = 'ssl';
        } else {
             $scheme = 'tcp';
        }
        $uriStr = "$scheme://" . $request->getHost() . ':' . $request->getPort();
        return new Uri($uriStr);
    }
    
    /**
     * @param Artax\Uri $socketUri
     * @return Artax\Network\Stream
     */
    protected function makeStream(Uri $socketUri) {
        if (strcmp('ssl', $socketUri->getScheme())) {
            return new SocketStream($this->mediator, $socketUri);
        } else {
            return new SslSocketStream($this->mediator, $socketUri, $this->sslOptions);
        }
    }
    
    /**
     * @param Artax\Network\Stream $stream
     * @return void
     */
    protected function checkinStream(Stream $stream) {
        $socketUriString = $stream->getUri();
        $this->sockPool[$socketUriString]->attach($stream, false);
        $this->mediator->notify(self::EVENT_STREAM_CHECKIN, $stream);
    }
    
    /**
     * @param Artax\Network\Stream $stream
     * @return void
     */
    protected function closeStream(Stream $stream) {
        $stream->close();
        $socketUriString = $stream->getUri();
        $this->sockPool[$socketUriString]->detach($stream);
    }
    
    /**
     * Close all open socket streams
     * 
     * @return int Returns the number of socket connections closed
     */
    public function closeAllStreams() {
        $connsClosed = 0;
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $stream) {
                $stream->close();
                ++$connsClosed;
            }
        }
        $this->sockPool = array();
        return $connsClosed;
    }
    
    /**
     * Close any open socket streams to the specified host
     * 
     * @param string $host
     * @return int Returns the number of socket connections closed
     */
    public function closeStreamsByHost($host) {
        $connsClosed = 0;
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $stream) {
                if ($stream->getHost() == $host) {
                    $stream->close();
                    ++$connsClosed;
                }
            }
        }
        $this->sockPool = array_filter($this->sockPool);
        return $connsClosed;
    }
    
    /**
     * Close all socket streams that have been idle longer than the specified number of seconds
     * 
     * @param int $maxInactivitySeconds
     * @return int Returns the number of socket connections closed
     */
    public function closeIdleStreams($maxInactivitySeconds) {
        $maxInactivitySeconds = (int) $maxInactivitySeconds;
        $connsClosed = 0;
        $now = time();
        
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $stream) {
                if ($now - $stream->getLastActivityTimestamp() > $maxInactivitySeconds) {
                    $stream->close();
                    ++$connsClosed;
                }
            }
        }
        
        return $connsClosed;
    }
    
    /**
     * @param Artax\Http\Request $request
     * @return void
     */
    protected function writeRequest(Request $request) {
        $s = $this->requestStateMap->offsetGet($request);
        
        if ($s->state == ClientState::SENDING_REQUEST_HEADERS) {
            $this->writeRequestHeaders($request, $s);
        }
        
        if ($s->state == ClientState::SENDING_BUFFERED_REQUEST_BODY) {
            $this->writeBufferedRequestBody($request, $s);
        }
        
        if ($s->state == ClientState::SENDING_STREAM_REQUEST_BODY) {
            $this->writeStreamingRequestBody($request, $s);
        }
    }
    
    /**
     * @param Artax\Http\Request $request
     * @param StdClass $s
     * @return void
     */
    protected function writeRequestHeaders(Request $request, StdClass $s) {
        $rawHeaders = $this->buildRawRequestHeaders($request);
        $rawHeaderBytes = strlen($rawHeaders);
        
        if ($s->headerBytesSent < $rawHeaderBytes) {
            $dataToSend = substr($rawHeaders, $s->headerBytesSent);
            if ($bytesSent = $s->stream->write($dataToSend)) {
                $s->headerBytesSent += $bytesSent;
            } elseif (false === $bytesSent) {
                throw new NetworkException();
            }
        }
        
        if ($s->headerBytesSent >= $rawHeaderBytes) {
            $requestBodyStream = $request->getBodyStream();
            $hasStreamBody = !empty($requestBodyStream);
            
            if (!($hasStreamBody || $request->getBody())) {
                $s->state = ClientState::READING_HEADERS;
            } elseif ($requestBodyStream) {
                $s->state = ClientState::SENDING_STREAM_REQUEST_BODY;
            } else {
                $s->state = ClientState::SENDING_BUFFERED_REQUEST_BODY;
            }
        }
    }
    
    /**
     * @param Artax\Http\Request $request
     * @return string
     */
    protected function buildRawRequestHeaders(Request $request) {
        if ($this->useProxyStyleRequests) {
            $requestLine = $request->getProxyRequestLine();
        } else {
            $requestLine = $request->getRequestLine();
        }
        $rawHeaders = $request->getRawHeaders();
        
        return "$requestLine\r\n$rawHeaders\r\n";
    }
    
    /**
     * @param Artax\Http\Request $request
     * @param StdClass $s
     * @return void
     */
    protected function writeBufferedRequestBody(Request $request, StdClass $s) {
        $requestBody = $request->getBody();
        $requestBodyBytes = strlen($requestBody);
        
        if ($s->bodyBytesSent < $requestBodyBytes) {
            $dataToSend = substr($requestBody, $s->bodyBytesSent);
            
            if ($bytesSent = $s->stream->write($dataToSend)) {
                $s->bodyBytesSent += $bytesSent;
            } elseif (false === $bytesSent) {
                throw new NetworkException();
            }
        }
        
        if ($s->bodyBytesSent >= $requestBodyBytes) {
            $s->state = ClientState::READING_HEADERS;
        }
    }
    
    /**
     * @param Artax\Http\Request $request
     * @param StdClass $s
     * @return void
     */
    protected function writeStreamingRequestBody(Request $request, StdClass $s) {
        $requestBody = $request->getBodyStream();
        
        fseek($requestBody, 0, SEEK_END);
        $requestBodyBytes = ftell($requestBody);
        rewind($requestBody);
        
        $bodyBuffer = null;
        
        while (true) {
            if (is_null($bodyBuffer)) {
                $readData = @fread($requestBody, $this->ioChunkSize);
                if (false === $readData) {
                    throw new RuntimeException(
                        "Failed reading request body from $requestBody"
                    );
                }
                
                $chunkLength = strlen($readData);
                $bodyBuffer = dechex($chunkLength) . "\r\n$readData\r\n";
                $bodyBufferBytes = strlen($bodyBuffer);
                $bodyBufferBytesSent = 0;
            }
            
            $dataToSend = substr($bodyBuffer, $bodyBufferBytesSent);
            
            if ($bytesSent = $s->stream->write($dataToSend)) {
                $bodyBufferBytesSent += $bytesSent;
                if ($bodyBufferBytesSent < $bodyBufferBytes) {
                    continue;
                }
                
                $s->bodyBytesSent += $chunkLength;
                
                if ($s->bodyBytesSent == ftell($requestBody) && $bodyBuffer == "0\r\n\r\n") {
                    $s->state = ClientState::READING_HEADERS;
                    return;
                } else {
                    $bodyBuffer = null;
                }
            } elseif (false === $bytesSent) {
                throw new NetworkException();
            }
        }
    }
    
    /**
     * @param Artax\Http\Request $request
     * @return void
     */
    protected function readResponse(Request $request) {
        $s = $this->requestStateMap->offsetGet($request);
        
        if ($s->state == ClientState::READING_HEADERS) {
            if (!$this->readHeaders($s)) {
                return;
            }
        }
        
        if ($s->state < ClientState::RESPONSE_RECEIVED) {
            if (!$this->readBody($s)) {
                return;
            }
        }
        
        if ($this->shouldCloseConnection($s->response)) {
            $this->closeStream($s->stream);
        } else {
            $this->checkinStream($s->stream);
        }
        
        if ($this->canRedirect($request, $s->response, $s->redirectHistory)) {
            $this->redirect($request, $s);
        } else {
            $this->mediator->notify(self::EVENT_RESPONSE, $s->key, $s->response);
        }
    }
    
    /**
     * @param StdClass $s
     * @return bool
     */
    protected function readHeaders(StdClass $s) {
        while ($readData = $s->stream->read($this->ioChunkSize)) {
            $s->responseBuffer .= $readData;
            $s->responseBuffer = ltrim($s->responseBuffer);
            
            if (false === ($bodyStartPos = strpos($s->responseBuffer, "\r\n\r\n"))) {
                continue;
            }
            
            $startLineAndHeaders = substr($s->responseBuffer, 0, $bodyStartPos);
            list($startLine, $headers) = explode("\r\n", $startLineAndHeaders, 2);
            
            $s->responseBuffer = substr($s->responseBuffer, $bodyStartPos + 4);
            
            $s->response->setStartLine($startLine);
            $s->response->setAllRawHeaders($headers);
            
            if (!$this->responseAllowsEntityBody($s->response)) {
                $s->state = ClientState::RESPONSE_RECEIVED;
            } if ($this->isChunked($s->response)) {
                $s->state = ClientState::READING_CHUNKS;
            } elseif ($s->response->hasHeader('Content-Length')) {
                $s->state = ClientState::READING_UNTIL_LENGTH_REACHED;
            } else {
                $s->state = ClientState::READING_UNTIL_CLOSE;
            }
            
            return true;
        }
        
        if (false === $readData) {
            throw new NetworkException();
        }
        
        return false;
    }
    
    /**
     * @param Artax\Http\Response $response
     * @return bool
     */
    protected function responseAllowsEntityBody(Response $response) {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode == 204) {
            return false;
        }
        if ($statusCode == 304) {
            return false;
        }
        if ($statusCode >= 100 && $statusCode < 200) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @param Artax\Http\Response $response
     * @return bool
     */
    protected function isChunked(Response $response) {
        if (!$response->hasHeader('Transfer-Encoding')) {
            return false;
        }
        
        $transferEncoding = $response->getHeader('Transfer-Encoding');
        
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
        // 
        // "If a Transfer-Encoding header field (section 14.41) is present and has any value other 
        // than "identity", then the transfer-length is defined by use of the "chunked" 
        // transfer-coding (section 3.6), unless the message is terminated by closing the 
        // connection.
        $isChunked = strtolower($transferEncoding) !== 'identity';
        
        return $isChunked;
    }
    
    /**
     * @param StdClass $s
     * @return bool
     */
    protected function readBody(StdClass $s) {
        if (!$responseBodyStream = $s->response->getBodyStream()) {
            $responseBodyStream = $this->openMemoryStream();
            $s->response->setBody($responseBodyStream);
            if (false === @fwrite($responseBodyStream, $s->responseBuffer)) {
                throw new RuntimeException("Failed writing to memory stream $responseBodyStream");
            }
        }
        
        while ($readData = $s->stream->read($this->ioChunkSize)) {
            if (false === @fwrite($responseBodyStream, $readData)) {
                throw new RuntimeException("Failed writing to memory stream $responseBodyStream");
            }
        }
        
        if ($s->state == ClientState::READING_UNTIL_LENGTH_REACHED) {
            $bytesRecd = ftell($responseBodyStream);
            if ($bytesRecd >= $s->response->getHeader('Content-Length')) {
                $s->state = ClientState::RESPONSE_RECEIVED;
            }
        } elseif ($s->state == ClientState::READING_CHUNKS) {
            fseek($responseBodyStream, -7, SEEK_END);
            if ("\r\n0\r\n\r\n" == stream_get_contents($responseBodyStream)) {
                stream_filter_prepend($responseBodyStream, 'dechunk');
                $s->state = ClientState::RESPONSE_RECEIVED;
            }
        } elseif ($s->state == ClientState::READING_UNTIL_CLOSE && $readData === '') {
            $s->state = ClientState::RESPONSE_RECEIVED;
        }
        
        if ($s->state == ClientState::RESPONSE_RECEIVED) {
            rewind($responseBodyStream);
            return true;
        }
        
        if (false === $readData) {
            throw new NetworkException();
        }
        
        return false;
    }
    
    /**
     * @param Artax\Http\Response $response
     * @return bool
     */
    protected function shouldCloseConnection(Response $response) {
        if (!$response->hasHeader('Connection')) {
            return false;
        }
        if (strcmp($response->getHeader('Connection'), 'close')) {
            return false;
        }
        return true;
    }
    
    /**
     * @return resource
     * @throws RuntimeException
     */
    protected function openMemoryStream() {
        if (false !== ($stream = @fopen('php://temp', 'r+'))) {
            return $stream;
        }
        throw new RuntimeException('Failed opening in-memory stream');
    }
    
    /**
     * @param Artax\Http\Request $request
     * @param Artax\Http\Response $response
     * @param array $redirectHistory
     * @return bool
     */
    protected function canRedirect(Request $request, Response $response, array $redirectHistory) {
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        if (!empty($redirectHistory) && count($redirectHistory) >= $this->maxRedirects) {
            return false;
        }
        
        $requestMethod = $request->getMethod();
        if (!$this->allowNonStandardRedirects && !in_array($requestMethod, array('GET', 'HEAD'))) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @param Artax\Http\Request $request
     * @param StdClass $s
     * @return void
     */
    protected function redirect(Request $request, StdClass $s) {
        $oldLocation = $request->getUri();
        $newLocation = $this->normalizeRedirectLocation($request, $s->response);
        
        $s->redirectHistory[] = $oldLocation;
        
        if (in_array($newLocation, $s->redirectHistory)) {
            $s->response->appendHeader(
                'Warning',
                "199 Infinite redirect loop detected: cannot redirect to $newLocation"
            );
            return;
        }
        
        $newRequest = new StdRequest($newLocation, $request->getMethod());
        $newRequest->setHttpVersion($request->getHttpVersion());
        $newRequest->setAllHeaders($request->getAllHeaders());
        $newRequest->setHeader('Host', $newRequest->getAuthority());
        
        if ($newRequest->allowsEntityBody()) {
            $streamBody = $request->getBodyStream();
            if ($streamBody) {
                rewind($streamBody);
                $newRequest->setBody($streamBody);
            } else {
                $newRequest->setBody($request->getBody());
            }
        }
        
        $newState = $this->makeStateObj($s->key);
        $newState->response->setPreviousResponse($s->response);
        $newState->redirectHistory = $s->redirectHistory;
        
        $this->requestStateMap->detach($request);
        $this->requestStateMap->attach($newRequest, $newState);
        
        $this->mediator->notify(self::EVENT_REDIRECT, $s->key, $oldLocation, $newLocation);
    }

    /**
     * @param Artax\Http\Request $prevRequest
     * @param Artax\Http\Response $prevResponse
     * @return string
     */
    protected function normalizeRedirectLocation(Request $prevRequest, Response $prevResponse) {
        $locationHeader = $prevResponse->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $prevRequest->getScheme() . '://' . $prevRequest->getRawAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $prevResponse->setHeader('Location', $newLocation);
            $prevResponse->appendHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
        } else {
            $newLocation = $locationHeader;
        }
        
        return $newLocation;
    }
}