<?php

namespace Artax\Http;

use Spl\Mediator,
    Spl\TypeException,
    StdClass,
    Traversable,
    Exception,
    RuntimeException,
    Artax\Http\Exceptions\ClientException,
    Artax\Http\Exceptions\ConnectException,
    Artax\Http\Exceptions\TimeoutException,
    Artax\Http\Exceptions\TransferException,
    Artax\Http\Exceptions\InfiniteRedirectException,
    Artax\Http\Exceptions\MaxConcurrencyException;

class Client {
    
    /**
     * @var int
     */
    const STATE_SENDING_REQUEST_HEADERS = 2;
    
    /**
     * @var int
     */
    const STATE_SENDING_BUFFERED_REQUEST_BODY = 4;
    
    /**
     * @var int
     */
    const STATE_STREAMING_REQUEST_BODY = 8;
    
    /**
     * @var int
     */
    const STATE_READING_RESPONSE_HEADERS = 16;
    
    /**
     * @var int
     */
    const STATE_READING_RESPONSE_TO_CLOSE = 32;
    
    /**
     * @var int
     */
    const STATE_READING_RESPONSE_TO_LENGTH = 64;
    
    /**
     * @var int
     */
    const STATE_READING_RESPONSE_CHUNKS = 128;
    
    /**
     * @var int
     */
    const STATE_RESPONSE_COMPLETE = 256;
    
    /**
     * @var int
     */
    const STATE_ERROR = 512;
    
    /**
     * @var string
     */
    const EVENT_IO_READ_HEADERS = 'artax.http.client.io.read.headers';
    
    /**
     * @var string
     */
    const EVENT_IO_READ_BODY = 'artax.http.client.io.read.body';
    
    /**
     * @var string
     */
    const EVENT_IO_WRITE_HEADERS = 'artax.http.client.io.write.headers';
    
    /**
     * @var string
     */
    const EVENT_IO_WRITE_BODY = 'artax.http.client.io.write.body';
    
    /**
     * @var string
     */
    const EVENT_REDIRECT = 'artax.http.client.redirect';
    
    /**
     * @var string
     */
    const EVENT_RESPONSE_COMPLETE = 'artax.http.client.response.complete';
    
    /**
     * @var string
     */
    const EVENT_CONN_CHECKOUT = 'artax.http.client.connection.checkout';
    
    /**
     * @var string
     */
    const EVENT_CONN_CHECKIN = 'artax.http.client.connection.checkin';
    
    /**
     * @var string
     */
    const EVENT_CONN_OPEN = 'artax.http.client.connection.open';
    
    /**
     * @var string
     */
    const EVENT_CONN_CLOSE = 'artax.http.client.connection.close';
    
    /**
     * @var string
     */
    protected $userAgent = 'Artax-Http/0.1 (PHP5.3+)';
    
    /**
     * @var int
     */
    protected $activityTimeout = 10;
    
    /**
     * @var int
     */
    protected $maxRedirects = 10;
    
    /**
     * @var int
     */
    protected $currentRedirectIteration = 0;
    
    /**
     * @var array
     */
    protected $redirectHistory;
    
    /**
     * @var bool
     */
    protected $nonStandardRedirectFlag = false;
    
    /**
     * @var bool
     */
    protected $useProxyRequestLine = false;
    
    /**
     * @var int
     */
    protected $chunkSize = 8192;
    
    /**
     * @var string
     */
    protected $acceptEncoding = 'identity';
    
    /**
     * @var array
     */
    protected $multiStateMap;
    
    /**
     * @var array
     */
    protected $multiRequestQueue;
    
    /**
     * @var Spl\Mediator
     */
    protected $mediator;
    
    /**
     * @var ConnectionManager
     */
    protected $connMgr;
    
    /**
     * @param ConnectionManager $connMgr
     * @param Mediator $mediator
     * @return void
     */
    public function __construct(ConnectionManager $connMgr, Mediator $mediator) {
        $this->connMgr = $connMgr;
        $this->mediator = $mediator;
    }
    
    /**
     * Request an HTTP resource and retrieve the response
     * 
     * @param Request $request
     * @return Response
     */
    public function send(Request $request) {
        $this->doMultiStreamSelect(array($request));
        $state = reset($this->multiStateMap);
        
        if ($state->response instanceof Response) {
            return $state->response;
        } else {
            $caughtException = $state->response;
            throw new ClientException($caughtException->getMessage(), null, $caughtException);
        }
    }
    
    /**
     * Send an HTTP request and don't bother to wait for a response
     * 
     * @param Request $request
     * @return void
     * @throws Artax\Http\Exceptions\ConnectException
     * @throws Artax\Http\Exceptions\TransferException
     */
    public function sendAsync(Request $request) {
        $state = new StdClass();
        $state->status = self::STATE_SENDING_REQUEST_HEADERS;
        $state->request = $this->normalizeRequestHeaders($request);
        $state->totalBytesSent = 0;
        
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        
        $state->conn = $this->connMgr->makeConnection($scheme, $host, $port, $flags);
        $state->conn->connect();
        
        while ($state->status < self::STATE_READING_RESPONSE_HEADERS) {
            $this->sendRequest($state);
        }
        
        $this->connMgr->close($state->conn);
    }
    
    /**
     * Retrieve multiple HTTP resources in parallel
     * 
     * Responses are returned in an array with the same "keys" as the original request list. If an
     * error occurs during the retrieval of one resource, the relevant exception will be returned
     * in place of a Response object for that key.
     * 
     * @param mixed $requests An array, StdClass or Traversable object
     * @return ClientMultiResponse
     */
    public function sendMulti($requests) {
        $this->validateMultiRequestTraversable($requests);
        $this->doMultiStreamSelect($requests);
        $responses = array_map(function($s) { return $s->response; }, $this->multiStateMap);
        
        return new ClientMultiResponse($responses);
    }
    
    /**
     * @param mixed $requests An array, StdClass or Traversable object
     * @return void
     * @throws Spl\TypeException
     */
    private function validateMultiRequestTraversable($requests) {
        if (!(is_array($requests)
            || $requests instanceof Traversable
            || $requests instanceof StdClass
        )) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new TypeException(
                "Client::sendMulti expects an array, StdClass or Traversable object at Argument " .
                "1; $type provided"
            );
        }
        
        foreach ($requests as $request) {
            if ($request instanceof Request) {
                continue;
            }
            $type = is_object($request) ? get_class($requests) : gettype($request);
            throw new TypeException(
                "Client::sendMulti requires that each element of the list passed to Argument " .
                "1 implement Artax\\Http\\Request; $type provided"
            );
        }
    }

    /**
     * Set the number of seconds before a socket connection attempt times out.
     * 
     * @param int $seconds
     * @return void
     */
    public function setConnectTimout($seconds) {
        $this->connMgr->setConnectTimeout($seconds);
    }
    
    /**
     * Set the amount of time without data transfer before an IO timeout occurs on a connection
     * 
     * Any value of zero or less will be interpreted as "no timeout."
     * 
     * @param int $seconds
     * @return void
     */
    public function setActivityTimeout($seconds) {
        $this->activityTimeout = (int) $seconds;
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
        $this->connMgr->setSslOptions = $options;
    }

    /**
     * Set the maximum number of redirects allowed to fulfill a request. Defaults to 10.
     * 
     * Infinite redirection loops are detected immediately regardless of this setting.
     * 
     * @param int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->maxRedirects = (int) $maxRedirects;
    }

    /**
     * Set the maximum number of simultaneous connections allowed per host
     * 
     * The default value is 5. If the maximum number of simultaneous connections to a specific host
     * are already in use during a `Client::sendMulti` operation, further requests to that host are
     * queued until one of the existing in-use connections to that host becomes available.
     * 
     * Asynchronous requests are not subject to the concurrency limit.
     * 
     * @param int $maxConnections
     * @return void
     */
    public function setHostConcurrencyLimit($maxConnections) {
        $this->connMgr->setHostConcurrencyLimit($maxConnections);
    }
    
    /**
     * Should the client transparently redirect requests not using GET or HEAD? Defaults to false.
     * 
     * According to RFC2616-10.3, "If the 301 status code is received in response to a request other
     * than GET or HEAD, the user agent MUST NOT automatically redirect the request unless it can be
     * confirmed by the user, since this might change the conditions under which the request was
     * issued."
     * 
     * This directive, if set to true, serves as confirmation that requests made using methods other
     * than GET/HEAD may be redirected automatically.
     * 
     * @param bool $boolFlag
     * @return void
     */
    public function allowNonStandardRedirects($boolFlag) {
        $this->nonStandardRedirectFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Enable/disable the use of proxy-style absolute URI request lines when sending requests
     * 
     * @param bool $boolFlag
     * @return void
     */
    public function useProxyRequestLine($boolFlag) {
        $this->useProxyRequestLine = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Whether or not the client should request compressed (gzip/deflate) entity bodies
     * 
     * This option is disabled by default. Note that even if this option is enabled, the client 
     * will only request encoded messages if PHP's zlib extension is installed.
     * 
     * @param bool $boolFlag
     * @return string The Accept-Encoding header value that will be used for requests
     */
    public function acceptEncodedContent($boolFlag) {
        $filteredBool = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $acceptedEncodings = $filteredBool ? $this->determineAcceptedEncodings() : 'identity';
        $this->acceptedEncodings = $acceptedEncodings;
        
        return $acceptedEncodings;
    }
    
    /**
     * @return string
     */
    protected function determineAcceptedEncodings() {
        $encodingsWeCanHandle = array();
        
        if (function_exists('gzdecode')) {
            $encodingsWeCanHandle[] = 'gzip';
        }
        if (function_exists('gzinflate')) {
            $encodingsWeCanHandle[] = 'deflate';
        }
        
        $encodingsWeCanHandle[] = 'identity';
        
        return implode(',', $encodingsWeCanHandle);
    }
    
    /**
     * @param mixed $requests A traversable list of Request objects
     * @return void
     */
    protected function doMultiStreamSelect($requests) {
        $this->multiStateMap = array();
        $this->multiRequestQueue = array();
        $this->buildMultiStateMap($requests);
        
        $readArr = $writeArr = $this->buildSelectableStreamArray();
        $ex = null;
        
        while (true) {
            if ($this->allRequestStatesAreComplete()) {
                return;
            }
            
            if (!stream_select($readArr, $writeArr, $ex, 1)) {
                continue;
            }
            
            foreach ($writeArr as $stateKey => $stream) {
                $state = $this->multiStateMap[$stateKey];
                if  (is_null($state)) {
                    continue;
                }
                try {
                    $this->sendRequest($state);
                } catch (ClientException $e) {
                    $state->status = self::STATE_ERROR;
                    $state->response = $e;
                }
            }
            
            foreach ($readArr as $stateKey => $stream) {
                $state = $this->multiStateMap[$stateKey];
                if  (is_null($state)) {
                    continue;
                }
                try {
                    $this->readResponse($state);
                } catch (ClientException $e) {
                    $state->status = self::STATE_ERROR;
                    $state->response = $e;
                }
            }
            
            if (!empty($this->multiRequestQueue)) {
                $this->buildMultiStateMap($this->multiRequestQueue);
            }
            
            $readArr = $writeArr = array();
            
            foreach ($this->getIncompleteStatesFromMultiStateMap() as $stateKey => $state) {
                $this->validateActivityTimeout($state->conn);
                
                if ($state->status < self::STATE_READING_RESPONSE_HEADERS) {
                    $writeArr[$stateKey] = $state->conn->getStream();
                } elseif ($state->status < self::STATE_RESPONSE_COMPLETE) {
                    $readArr[$stateKey] = $state->conn->getStream();
                }
            }
        }
    }
    
    /**
     * @param ClientConnection $conn
     * @throws TimeoutException
     */
    protected function validateActivityTimeout(ClientConnection $conn) {
        if ($this->activityTimeout < 1) {
            return;
        }
        if ($conn->hasBeenIdleFor($this->activityTimeout)) {
            throw new TimeoutException(
                'Connection to ' . $conn->getUri() . 'exceeded the max allowable ' .
                "activity timeout of {$this->activityTimeout} seconds"
            );
        }
    }
    
    /**
     * @return array
     */
    protected function getIncompleteStatesFromMultiStateMap() {
        $incompletes = array();
        $queuedStatesRemoved = array_filter($this->multiStateMap);
        foreach ($queuedStatesRemoved as $key => $state) {
            if (!$this->isComplete($state)) {
                $incompletes[$key] = $state;
            }
        }
        return $incompletes;
    }
    
    /**
     * @param mixed $requests A traversable array/StdClass/Traversable of Request instances
     * @return array
     */
    protected function buildMultiStateMap($requests) {
        foreach ($requests as $stateKey => $request) {
            $scheme = $request->getScheme();
            $host = $request->getHost();
            $port = $request->getPort();
            
            $state = new StdClass;
            
            try {
                $conn = $this->connMgr->checkout($scheme, $host, $port);
                unset($this->multiRequestQueue[$stateKey]);
            } catch (ConnectException $e) {
                $state->status = self::STATE_ERROR;
                $state->response = $e;
                $this->multiStateMap[$stateKey] = $state;
                continue;
            } catch (MaxConcurrencyException $e) {
                $this->multiStateMap[$stateKey] = null;
                $this->multiRequestQueue[$stateKey] = $request;
                continue;
            }
            
            $state->conn = $conn;
            $state->status = self::STATE_SENDING_REQUEST_HEADERS;
            $state->request = $this->normalizeRequestHeaders($request);
            $state->totalBytesSent = 0;
            
            $this->multiStateMap[$stateKey] = $state;
        }
    }
    
    /**
     * @return array
     */
    protected function buildSelectableStreamArray() {
        $streams = array();
        foreach ($this->multiStateMap as $stateKey => $state) {
            if (!is_null($state) && !$this->isComplete($state)) {
                $streams[$stateKey] = $state->conn->getStream();
            }
        }
        
        return $streams;
    }
    
    /**
     * @return bool
     */
    protected function allRequestStatesAreComplete() {
        $queuedStatesRemoved = array_filter($this->multiStateMap);
        $completedStates = array_map(array($this, 'isComplete'), $queuedStatesRemoved);
        return array_sum($completedStates) == count($this->multiStateMap);
    }
    
    /**
     * @return bool
     */
    protected function isComplete(StdClass $state) {
        return $state->status >= self::STATE_RESPONSE_COMPLETE;
    }
    
    /**
     * @param StdClass $state
     * @return void
     */
    protected function sendRequest(StdClass $state) {
        if ($state->status == self::STATE_SENDING_REQUEST_HEADERS) {
            $this->writeRequestHeaders($state);
        }
        
        if ($state->status == self::STATE_SENDING_BUFFERED_REQUEST_BODY) {
            $this->writeBufferedRequestBody($state);
        }
        
        if ($state->status == self::STATE_STREAMING_REQUEST_BODY) {
            $this->writeStreamingRequestBody($state);
        }
    }
    
    /**
     * @param StdClass $state
     * @return void
     */
    protected function writeRequestHeaders(StdClass $state) {
        $state->requestHeaders = isset($state->requestHeaders)
            ? $state->requestHeaders
            : $this->buildRawRequestHeaders($state->request);
            
        $state->requestHeaderBytes = isset($state->requestHeaderBytes)
            ? $state->requestHeaderBytes
            : strlen($state->requestHeaders);
            
        $state->requestHeaderBytesSent = isset($state->requestHeaderBytesSent)
            ? $state->requestHeaderBytesSent
            : 0;
        
        if ($state->requestHeaderBytesSent < $state->requestHeaderBytes) {
            $dataToSend = substr($state->requestHeaders, $state->requestHeaderBytesSent);
            
            if ($bytesSent = $state->conn->writeData($dataToSend)) {
                $state->requestHeaderBytesSent += $bytesSent;
                $state->totalBytesSent += $bytesSent;
                $actualDataSent = substr($dataToSend, 0, $bytesSent);
                
                $this->mediator->notify(
                    self::EVENT_IO_WRITE_HEADERS,
                    $this->getInitialRequestFromState($state),
                    $actualDataSent
                );
                
            } elseif (false === $bytesSent) {
                throw new TransferException();
            }
        }
        
        if ($state->requestHeaderBytesSent >= $state->requestHeaderBytes) {        
            unset(
                $state->requestHeaders,
                $state->requestHeaderBytes,
                $state->requestHeaderBytesSent
            );
            
            $requestBodyStream = $state->request->getBodyStream();
            
            if (empty($requestBodyStream) && !$state->request->getBody()) {
                $state->status = self::STATE_READING_RESPONSE_HEADERS;
            } elseif ($requestBodyStream) {
                $state->status = self::STATE_STREAMING_REQUEST_BODY;
            } else {
                $state->status = self::STATE_SENDING_BUFFERED_REQUEST_BODY;
            }
        }
    }
    
    /**
     * @param StdClass $state
     * @return void
     */
    protected function writeBufferedRequestBody(StdClass $state) {
        $state->requestBody = isset($state->requestBody)
            ? $state->requestBody
            : $state->request->getBody();
        
        $state->requestBodyBytes = isset($state->requestBodyBytes)
            ? $state->requestBodyBytes
            : strlen($state->requestBody);
            
        $state->requestBodyBytesSent = isset($state->requestBodyBytesSent)
            ? $state->requestBodyBytesSent
            : 0;
        
        if ($state->requestBodyBytesSent < $state->requestBodyBytes) {
            $dataToSend = substr($state->requestBody, $state->requestBodyBytesSent);
            
            if ($bytesSent = $state->conn->writeData($dataToSend)) {
                $state->requestBodyBytesSent += $bytesSent;
                $state->totalBytesSent += $bytesSent;
                $actualDataSent = substr($dataToSend, 0, $bytesSent);
                
                $this->mediator->notify(
                    self::EVENT_IO_WRITE_BODY,
                    $this->getInitialRequestFromState($state),
                    $actualDataSent
                );
            } elseif (false === $bytesSent) {
                throw new TransferException();
            }
        }
        
        if ($state->requestBodyBytesSent >= $state->requestBodyBytes) {
            unset(
                $state->requestBody,
                $state->requestBodyBytes,
                $state->requestBodyBytesSent
            );
            $state->status = self::STATE_READING_RESPONSE_HEADERS;
        }
    }
    
    /**
     * @param StdClass $state
     * @return void
     */
    protected function writeStreamingRequestBody(StdClass $state) {
        
        $entityBody = $state->request->getBodyStream();
        
        fseek($entityBody, 0, SEEK_END);
        $bodyStreamLength = ftell($entityBody);
        rewind($entityBody);
        
        while (true) {
            
            if (empty($state->bodyBuffer)) {
                
                if (false === ($readData = @fread($entityBody, $this->chunkSize))) {
                    throw new RuntimeException(
                        "Failed reading data from request entity body $entityBody"
                    );
                }
                
                $chunkLength = strlen($readData);
                $state->bodyBuffer = dechex($chunkLength) . "\r\n$readData\r\n";
                $state->bodyBufferBytes = strlen($state->bodyBuffer);
                $state->bodyBufferBytesSent = 0;
            }
            
            if ($bytesSent = $state->conn->writeData($state->bodyBuffer)) {
                $dataSent = substr($state->bodyBuffer, $state->bodyBufferBytesSent, $bytesSent);
                
                $state->bodyBufferBytesSent += $bytesSent;
                $state->totalBytesSent += $bytesSent;
                
                $this->mediator->notify(
                    self::EVENT_IO_WRITE_BODY,
                    $this->getInitialRequestFromState($state),
                    $dataSent
                );
                
                if ($state->bodyBufferBytesSent >= $state->bodyBufferBytes) {
                    if ($bodyStreamLength == ftell($entityBody)
                        && $state->bodyBuffer == "0\r\n\r\n"
                    ) {
                        $state->status = self::STATE_READING_RESPONSE_HEADERS;
                        return;
                    }
                    $state->bodyBuffer = null;
                }
                
            } elseif (false === $bytesSent) {
                throw new TransferException(
                    "Transfer failure: connection lost after {$state->totalBytesSent} request " .
                    "bytes sent"
                );
            }
        }
    }
    
    /**
     * @param StdClass $state
     * @return void
     */
    protected function readResponse(StdClass $state) {
        
        $state->response = empty($state->response) ? new ClientResponse() : $state->response;
        
        if ($state->status == self::STATE_READING_RESPONSE_HEADERS) {
            if (!$this->receiveHeaders($state)) {
                return;
            }
        }
        
        if ($state->status != self::STATE_RESPONSE_COMPLETE) {
            if (!$this->receiveBody($state)) {
                return;
            }
        }
        
        if ($state->response->hasHeader('Content-Encoding')) {
            $encoding = strtolower($state->response->getHeader('Content-Encoding'));
            $decoded = $this->decodeEntityBody($encoding, $state->response->getBody());
            $state->response->setBody($decoded);
        }
        
        if ($this->shouldCloseConnection($state->response)) {
            $this->connMgr->close($state->conn);
        } else {
            $this->connMgr->checkin($state->conn);
        }
        
        if ($this->canRedirect($state)) {
            $this->redirect($state);
        } else {
            $this->mediator->notify(
                self::EVENT_RESPONSE_COMPLETE,
                $this->getInitialRequestFromState($state),
                $state->response
            );
        }
    }
    
    /**
     * @param StdClass $state
     * @return bool
     */
    protected function receiveHeaders(StdClass $state) {
        
        $state->totalBytesReceived = isset($state->totalBytesReceived)
            ? $state->totalBytesReceived
            : 0;
        
        $state->responseHeaderBuffer = isset($state->responseHeaderBuffer)
            ? $state->responseHeaderBuffer
            : '';
        
        $state->headerBytesReceived = isset($state->headerBytesReceived)
            ? $state->headerBytesReceived
            : 0;
        
        while ($line = $state->conn->readLine()) {
            $state->responseHeaderBuffer .= $line;
            $bytesRecieved = strlen($line);
            $state->headerBytesReceived += $bytesRecieved;
            $state->totalBytesReceived += $bytesRecieved;
            
            $this->mediator->notify(
                self::EVENT_IO_READ_HEADERS,
                $this->getInitialRequestFromState($state),
                $line
            );
            
            if (substr($state->responseHeaderBuffer, -4) !== "\r\n\r\n") {
                continue;
            }
                
            list($startLine, $headers) = explode("\r\n", $state->responseHeaderBuffer, 2);
            
            $state->response->setStartLine($startLine);
            $state->response->setAllRawHeaders($headers);
            
            if (!$this->responseAllowsEntityBody($state->response)) {
                $state->status = self::STATE_RESPONSE_COMPLETE;
            } elseif ($this->isChunked($state->response)) {
                $state->status = self::STATE_READING_RESPONSE_CHUNKS;
            } elseif ($state->response->hasHeader('Content-Length')) {
                $state->status = self::STATE_READING_RESPONSE_TO_LENGTH;
            } else {
                $state->status = self::STATE_READING_RESPONSE_TO_CLOSE;
            }
            
            unset($state->responseHeaderBuffer, $state->headerBytesReceived);
            
            return true;
        }
        
        if (false === $line) {
            throw new TransferException(
                "Response retrieval error: transfer failed after {$state->totalBytesReceived} " .
                "bytes received"
            );
        }
        
        return false;
    }
    
    /**
     * @param StdClass $state
     * @return bool
     */
    protected function receiveBody(StdClass $state) {
        
        if (!$this->requestAllowsResponseBody($state->request)) {
            $state->status = self::STATE_RESPONSE_COMPLETE;
            return true;
        } elseif (!$responseBodyStream = $state->response->getBodyStream()) {
            $responseBodyStream = $this->openMemoryStream();
            $state->response->setBody($responseBodyStream);
        }
        
        $state->bodyBytesReceived = isset($state->bodyBytesReceived)
            ? $state->bodyBytesReceived
            : 0;
        
        while ($readData = $state->conn->readBytes($this->chunkSize)) {
            $bytesRecd = strlen($readData);
            if (false === @fwrite($responseBodyStream, $readData)) {
                throw new RuntimeException("Failed writing to in-memory stream $responseBodyStream");
            }
            
            $state->bodyBytesReceived += $bytesRecd;
            $state->totalBytesReceived += $bytesRecd;
            
            $this->mediator->notify(
                self::EVENT_IO_READ_BODY,
                $this->getInitialRequestFromState($state),
                $readData
            );
        }
        
        if ($state->status == self::STATE_READING_RESPONSE_TO_LENGTH) {
            if ($state->bodyBytesReceived >= $state->response->getHeader('Content-Length')) {
                $state->status = self::STATE_RESPONSE_COMPLETE;
            }
        } elseif ($state->status == self::STATE_READING_RESPONSE_CHUNKS) {
            fseek($responseBodyStream, -7, SEEK_END);
            if ("\r\n0\r\n\r\n" == stream_get_contents($responseBodyStream)) {
                stream_filter_prepend($responseBodyStream, 'dechunk');
                $state->status = self::STATE_RESPONSE_COMPLETE;
            }
        } elseif ($state->status == self::STATE_READING_RESPONSE_TO_CLOSE && $readData === '') {
            $state->status = self::STATE_RESPONSE_COMPLETE;
        }
        
        if ($state->status == self::STATE_RESPONSE_COMPLETE) {
            rewind($responseBodyStream);
            unset($state->bodyBytesReceived);
            return true;
        }
        
        if (false === $readData) {
            throw new TransferException(
                "Response retrieval error: transfer failed after {$state->totalBytesReceived} " .
                "bytes received"
            );
        }
        
        return false;
    }
    
    /**
     * @param Response $response
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
     * @param Request $request
     * @return string
     */
    protected function buildRawRequestHeaders(Request $request) {
        return $request->getRequestLine() . "\r\n" . $request->getRawHeadersString() . "\r\n";
    }
    
    /**
     * @param Request $request
     * @return Request
     */
    protected function normalizeRequestHeaders(Request $request) {
        $request->setHeader('User-Agent', $this->userAgent);
        
        if (!$this->useProxyRequestLine) {
            $request->setHeader('Host', $request->getAuthority());
        } else {
            $request->removeHeader('Host');
        }
        
        if ($this->acceptEncoding !== 'identity') {
            $request->setHeader('Accept-Encoding', $this->acceptEncoding);
        } else {
            $request->removeHeader('Accept-Encoding');
        }
        
        if ($request->getBodyStream()) {
            $request->setHeader('Transfer-Encoding', 'chunked');
        } elseif ($request->allowsEntityBody() && $entityBody = $request->getBody()) {
            $request->setHeader('Content-Length', strlen($entityBody));
        } else {
            $request->removeHeader('Content-Length');
            $request->removeHeader('Transfer-Encoding');
        }
        
        return $request;
    }
    
    /**
     * @param Response $response
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
     * @param Request $request
     * @return bool
     */
    protected function requestAllowsResponseBody(Request $request) {
        return 'HEAD' != $request->getMethod();
    }
    
    /**
     * @param Response $response
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
     * @param string $encoding
     * @param string $entityBody
     * @return string
     */
    protected function decodeEntityBody($encoding, $entityBody) {
        switch ($encoding) {
            case 'gzip':
                return @gzdecode($entityBody) ?: $entityBody;
            case 'deflate':
                return @gzinflate($entityBody) ?: $entityBody;
        }
        
        return $entityBody;
    }

    /**
     * @param StdClass $state
     * @return bool
     */
    protected function canRedirect(StdClass $state) {
        if (!$state->response->hasHeader('Location')) {
            return false;
        }
        
        if (!empty($state->redirectHistory)
            && count($state->redirectHistory) >= $this->maxRedirects
        ) {
            return false;
        }
        
        $requestMethod = $state->request->getMethod();
        if (!$this->nonStandardRedirectFlag && !in_array($requestMethod, array('GET', 'HEAD'))) {
            return false;
        }
        
        return true;
    }

    /**
     * @param StdClass $state
     * @return void
     */
    protected function redirect(StdClass $state) {
        $oldLocation = $state->request->getRawUri();
        $newLocation = $this->normalizeRedirectLocation($state->request, $state->response);
        
        $state->redirectHistory[$oldLocation] = $state->request;
        
        if (isset($state->redirectHistory[$newLocation])) {
            throw new InfiniteRedirectException(
                "Infinite redirect loop detected: cannot redirect to $newLocation"
            );
        }
        
        $newRequest = new StdRequest($newLocation, $state->request->getMethod());
        $newRequest->setAllHeaders($state->request->getHeadersArray());
        $newRequest->setHttpVersion($state->request->getHttpVersion());
        
        if ($newRequest->allowsEntityBody()) {
            $streamBody = $state->request->getBodyStream();
            if ($streamBody) {
                rewind($streamBody);
                $newRequest->setBody($streamBody);
            } else {
                $newRequest->setBody($state->request->getBody());
            }
        }
        
        $newResponse = new ClientResponse();
        $newResponse->setPreviousResponse($state->response);
        
        $state->request = $newRequest;
        $state->response = $newResponse;
        
        $state->totalBytesSent = 0;
        $state->totalBytesReceived = 0;
        $state->status = self::STATE_SENDING_REQUEST_HEADERS;
        
        $this->mediator->notify(
            self::EVENT_REDIRECT,
            $this->getInitialRequestFromState($state),
            "REDIRECTING $oldLocation ---> $newLocation"
        );
        
        $scheme = $newRequest->getScheme();
        $host = $newRequest->getHost();
        $port = $newRequest->getPort();
        
        $state->conn = $this->connMgr->checkout($scheme, $host, $port);
    }
    
    /**
     * @param StdClass $state
     * @return Request
     */
    protected function getInitialRequestFromState(StdClass $state) {
        if (empty($state->redirectHistory)) {
            return $state->request;
        } else {
            return reset($state->redirectHistory);
        }
    }

    /**
     * @param Request $lastRequest
     * @param Response $lastResponse
     * @return string
     */
    protected function normalizeRedirectLocation(Request $lastRequest, Response $lastResponse) {
        $locationHeader = $lastResponse->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $lastRequest->getScheme() . '://' . $lastRequest->getRawAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $lastResponse->setHeader('Location', $newLocation);
            $lastResponse->setHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
        } else {
            $newLocation = $locationHeader;
        }
        
        return $newLocation;
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
     * @return void
     */
    public function __destruct() {
        $this->connMgr->closeAll();
    }
}
