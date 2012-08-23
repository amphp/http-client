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
    Artax\Http\Exceptions\InfiniteRedirectException;

class Client {
    
    const STATE_SENDING_REQUEST_HEADERS = 2;
    const STATE_SENDING_BUFFERED_REQUEST_BODY = 4;
    const STATE_STREAMING_REQUEST_BODY = 8;
    const STATE_READING_RESPONSE_HEADERS = 16;
    const STATE_READING_RESPONSE_TO_CLOSE = 32;
    const STATE_READING_RESPONSE_TO_LENGTH = 64;
    const STATE_READING_RESPONSE_CHUNKS = 128;
    const STATE_RESPONSE_COMPLETE = 256;
    const STATE_ERROR = 512;
    
    const EVENT_IO_READ_HEADERS = 'artax.http.client.io.read.headers';
    const EVENT_IO_READ_BODY = 'artax.http.client.io.read.body';
    const EVENT_IO_WRITE_HEADERS = 'artax.http.client.io.write.headers';
    const EVENT_IO_WRITE_BODY = 'artax.http.client.io.write.body';
    const EVENT_REDIRECT = 'artax.http.client.redirect';
    const EVENT_RESPONSE_COMPLETE = 'artax.http.client.response.complete';
    
    /**
     * @var string
     */
    protected $userAgent = 'Artax-Http/0.1 (PHP5.3+)';
    
    /**
     * @var int
     */
    protected $activityTimeout = 30;
    
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
    protected $allowContentEncoding = false;
    
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
    protected $acceptedEncodings = 'identity';
    
    /**
     * @var Spl\Mediator
     */
    protected $mediator;
    
    /**
     * @var ConnectionManager
     */
    protected $connMgr;
    
    /**
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
        $completedStates = $this->doMultiStreamSelect(array($request));
        if ($completedStates[0]->response instanceof Response) {
            return $completedStates[0]->response;
        } else {
            $e = $completedStates[0]->response;
            throw new ClientException($e->getMessage(), null, $e);
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
        $state = new ClientRequestState();
        
        $state->request = $this->normalizeRequestHeaders($request);
        
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
        $completedStates = $this->doMultiStreamSelect($requests);
        $responsesAndErrors = array_map(function($s) { return $s->response; }, $completedStates);
        
        return new ClientMultiResponse($responsesAndErrors);
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
     *     $client->setOptions($options);
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
     * Asynchronous requests are not subject to the concurrency limit. They always open a new 
     * request in asynchronous mode and close the connection immediately after a request is sent.
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
     * Whether or not the client should accept encoded (gzip/deflate compressed) entity bodies
     * 
     * This option is disabled by default. Note that even if this option is enabled, the client 
     * will only request encoded messages if PHP's zlib extension is installed.
     * 
     * @param bool $boolFlag
     * @return string The Accept-Encoding header value that will be used for requests
     */
    public function allowContentEncoding($boolFlag) {
        $filteredBool = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->allowContentEncoding = $filteredBool;
        
        $acceptedEncodings = $filteredBool ? $this->determineAcceptedEncodings() : 'identity';
        $this->acceptedEncodings = $acceptedEncodings;
        
        return $acceptedEncodings;
    }
    
    /**
     * Determine which content encodings the current environment can support
     * 
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
     * Dispatch state holders to the appropriate machine as reads/writes become available
     * 
     * @param mixed $requests A traversable list of Request objects
     */
    protected function doMultiStreamSelect($requests, $dontCatchExceptions = false) {
        list($requestStates, $requestQueue) = $this->buildStreamSelectStateMap($requests);
        $readArr = $writeArr = $this->buildStreamSelectArray($requestStates);
        
        $ex = null;
        
        while (true) {
            
            if ($this->allClientRequestStatesHaveCompleted($requestStates)) {
                return $requestStates;
            }
            
            if (false === stream_select($readArr, $writeArr, $ex, 0, 500000)) {
                continue;
            }
            
            foreach ($writeArr as $stateKey => $stream) {
                $state = $requestStates[$stateKey];
                try {
                    $this->sendRequest($state);
                } catch (ClientException $e) {
                    $state->status = self::STATE_ERROR;
                    $state->response = $e;
                }
            }
            
            foreach ($readArr as $stateKey => $stream) {
                $state = $requestStates[$stateKey];
                try {
                    $this->readResponse($state);
                } catch (ClientException $e) {
                    $state->status = self::STATE_ERROR;
                    $state->response = $e;
                }
            }
            
            if ($requestQueue) {
                list($map, $queue) = $this->buildStreamSelectStateMap($requestQueue);
                $requestQueue = $queue;
                $requestStates = array_merge($requestStates, $map);
            }
            
            $readArr = $writeArr = array();
            $checkMe = array_filter($requestStates, function($s){ return !$this->isComplete($s); });
            
            foreach ($checkMe as $stateKey => $state) {
                if ($this->activityTimeout > 0
                    && $state->conn->hasBeenIdleFor($this->activityTimeout)
                ) {
                    throw new TimeoutException(
                        "{$state->conn} exceeded activity timeout: {$this->activityTimeout} seconds"
                    );
                } elseif ($state->status < self::STATE_READING_RESPONSE_HEADERS) {
                    $writeArr[$stateKey] = $state->conn->getStream();
                } elseif ($state->status < self::STATE_RESPONSE_COMPLETE) {
                    $readArr[$stateKey] = $state->conn->getStream();
                }
            }
        }
    }
    
    /**
     * Build an array of streams to select against from a map of request state holders
     * 
     * @param array $requestStates
     * @return array
     */
    protected function buildStreamSelectArray(array $requestStates) {
        $streams = array();
        
        foreach ($requestStates as $stateKey => $state) {
            if ($state && !$this->isComplete($state)) {
                $streams[$stateKey] = $state->conn->getStream();
            }
        }
        
        return $streams;
    }
    
    /**
     * Determines if retrieval for all states in the array has completed
     * 
     * @param array $requestStates
     * @return bool
     */
    protected function allClientRequestStatesHaveCompleted(array $requestStates) {
        $complete = array_map(array($this, 'isComplete'), $requestStates);
        return array_sum($complete) == count($requestStates);
    }
    
    /**
     * Is retrieval complete for the specified state?
     * 
     * @return bool
     */
    protected function isComplete(ClientRequestState $state) {
        return $state->status >= self::STATE_RESPONSE_COMPLETE;
    }
    
    /**
     * A state machine to send raw HTTP requests to a socket stream
     * 
     * @param ClientRequestState $state
     * @return void
     */
    protected function sendRequest(ClientRequestState $state) {
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
     * Send request headers
     * 
     * @param ClientRequestState $state
     * @return void
     */
    protected function writeRequestHeaders(ClientRequestState $state) {
        $rawHeaders = $this->buildRawRequestHeaders($state->request);
        $totalBytesToWrite = strlen($rawHeaders);
        
        if ($state->totalBytesSent < $totalBytesToWrite) {
            $dataToSend = substr($rawHeaders, $state->totalBytesSent);
            
            if ($bytesSent = $state->conn->writeData($dataToSend)) {
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
        
        if ($state->totalBytesSent < $totalBytesToWrite) {
            return;
        }
        
        $state->totalBytesSent = 0;
        
        if (!$state->request->getBodyStream() && !$state->request->getBody()) {
            $state->status = self::STATE_READING_RESPONSE_HEADERS;
        } else {
            $state->status = $state->request->getBodyStream()
                ? self::STATE_STREAMING_REQUEST_BODY
                : self::STATE_SENDING_BUFFERED_REQUEST_BODY;
        }
    }
    
    /**
     * Send buffered request body
     * 
     * @param ClientRequestState $state
     * @return void
     */
    protected function writeBufferedRequestBody(ClientRequestState $state) {
        $entityBody = $state->request->getBody();
        $totalBytesToWrite = strlen($entityBody);
        
        if ($state->totalBytesSent < $totalBytesToWrite) {
            $dataToSend = substr($entityBody, $state->totalBytesSent);
            
            if ($bytesSent = $state->conn->writeData($dataToSend)) {
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
        
        if ($state->totalBytesSent < $totalBytesToWrite) {
            return;
        }
        
        $state->status = self::STATE_READING_RESPONSE_HEADERS;
    }
    
    /**
     * Send streaming request entity body
     * 
     * @param ClientRequestState $state
     * @return void
     */
    protected function writeStreamingRequestBody(ClientRequestState $state) {
        
        $entityBody = $state->request->getBodyStream();
        
        while (true) {
            
            if (!$state->requestBodyStreamBuffer) {
                if (false === ($data = @fread($entityBody, $this->chunkSize))) {
                    throw new RuntimeException(
                        "Failed reading data from request entity body $entityBody"
                    );
                }
                
                $chunkLength = strlen($data);
                $state->requestBodyStreamPos = 0;
                $state->requestBodyStreamBuffer = dechex($chunkLength) . "\r\n$data\r\n";
                if (!$state->requestBodyStreamLength = strlen($state->requestBodyStreamBuffer)) {
                    $state->status = self::STATE_READING_RESPONSE_HEADERS;
                    return;
                }
            }
            
            if ($bytesSent = $state->conn->writeData($state->requestBodyStreamBuffer)) {
                $state->requestBodyStreamPos += $bytesSent;
                
                if ($state->requestBodyStreamPos == $state->requestBodyStreamLength) {
                    $state->totalBytesSent += $chunkLength;
                    $state->requestBodyStreamBuffer = null;
                }
                
                if ($state->totalBytesSent == ftell($entityBody)) {
                    $state->conn->writeData("0\r\n\r\n");
                    $state->status = self::STATE_READING_RESPONSE_HEADERS;
                    return;
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
     * Pass a state holder to the response state machine when its stream is ready for reading
     * 
     * @param ClientRequestState $state
     * @return void
     */
    protected function readResponse(ClientRequestState $state) {
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
        
        if ($state->responseBodyStream) {
            rewind($state->responseBodyStream);
            $state->response->setBody($state->responseBodyStream);
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
     * Receives HTTP message headers, returning true if response header retrieval is complete
     * 
     * ClientRequestState $state
     * @return bool
     */
    protected function receiveHeaders(ClientRequestState $state) {
        while ($line = $state->conn->readLine()) {
            $state->buffer .= $line;
            $bytesRecieved = strlen($line);
            $state->responseHeaderBytes += $bytesRecieved;
            $state->responseTotalBytes += $bytesRecieved;
            
            $this->mediator->notify(
                self::EVENT_IO_READ_HEADERS,
                $this->getInitialRequestFromState($state),
                $line
            );
            
            if (substr($state->buffer, -4) !== "\r\n\r\n") {
                continue;
            }
                
            list($startLine, $headers) = explode("\r\n", $state->buffer, 2);
            
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
            
            return true;
        }
        
        if (false === $line) {
            throw new TransferException(
                "Response retrieval failed: connection lost after {$state->responseTotalBytes} " .
                "bytes receved"
            );
        }
        
        return false;
    }
    
    /**
     * Receives HTTP message entity-body, returning true if entity body retrieval is complete
     * 
     * ClientRequestState $state
     * @return bool
     */
    protected function receiveBody(ClientRequestState $state) {
        if (!$this->requestAllowsResponseBody($state->request)) {
            $state->status = self::STATE_RESPONSE_COMPLETE;
            return true;
        }
        
        while ($readData = $state->conn->readBytes($this->chunkSize)) {
            $dataBytes = strlen($readData);
            fwrite($state->responseBodyStream, $readData);
            $state->responseTotalBytes += $dataBytes;
            
            $this->mediator->notify(
                self::EVENT_IO_READ_BODY,
                $this->getInitialRequestFromState($state),
                $readData
            );
        }
        
        if ($state->status == self::STATE_READING_RESPONSE_TO_LENGTH) {
            $entityLength = $state->responseTotalBytes - $state->responseHeaderBytes;
            if ($entityLength >= $state->response->getHeader('Content-Length')) {
                $state->status = self::STATE_RESPONSE_COMPLETE;
            }
        }
        
        if ($state->status == self::STATE_READING_RESPONSE_CHUNKS) {
            fseek($state->responseBodyStream, -7, SEEK_END);
            if ("\r\n0\r\n\r\n" == stream_get_contents($state->responseBodyStream)) {
                stream_filter_prepend($state->responseBodyStream, 'dechunk');
                $state->status = self::STATE_RESPONSE_COMPLETE;
            }
        }
        
        if ($state->status == self::STATE_READING_RESPONSE_TO_CLOSE && $readData === '') {
            $state->status = self::STATE_RESPONSE_COMPLETE;
        }
        
        if ($state->status == self::STATE_RESPONSE_COMPLETE) {
            return true;
        }
        
        if (false === $readData) {
            throw new TransferException(
                "Response retrieval failed: connection lost after {$state->responseTotalBytes} " .
                "bytes receved"
            );
        }
        
        return false;
    }
    
    /**
     * Build an array of request states for use with parallel stream selects
     * 
     * Returns a two-element array: the first element is a map of state objects; the second is
     * an array of Request objects that had to be queued because the maximum number of persistent
     * connections were already in use for the respective target host of each Request.
     * 
     * @param mixed $requests A traversable array/StdClass/Traversable of Request instances
     * @return array
     */
    protected function buildStreamSelectStateMap($requests) {
        $requestStates = array();
        $requestQueue = array();
        
        foreach ($requests as $stateKey => $request) {
            $state = new ClientRequestState();
            $state->request = $this->normalizeRequestHeaders($request);
            
            try {
                $scheme = $request->getScheme();
                $host = $request->getHost();
                $port = $request->getPort();
                $conn = $this->connMgr->checkout($scheme, $host, $port);
            } catch (ConnectException $e) {
                $state->status = self::STATE_ERROR;
                $state->response = $e;
                $requestStates[$stateKey] = $state;
                continue;
            }
            
            if (!$conn) {
                $requestStates[$stateKey] = null;
                $requestQueue[$stateKey] = $request;
                continue;
            } else {
                $state->status = self::STATE_SENDING_REQUEST_HEADERS;
                $state->conn = $conn;
                $state->response = $this->makeClientResponse();
                $state->responseBodyStream = $this->openMemoryStream();
                
                $requestStates[$stateKey] = $state;
            }
        }
        
        return array($requestStates, $requestQueue);
    }
    
    /**
     * A factory method test seam for generating ClientResponse instances
     * 
     * @return Artax\Http\ClientResponse
     */
    protected function makeClientResponse() {
        return new ClientResponse();
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
        $rawHeaders = $request->getRequestLine() . "\r\n";
        
        foreach ($request->getAllHeaders() as $header => $value) {
            $rawHeaders.= "$header: $value\r\n";
        }
        
        $rawHeaders.= "\r\n";
        
        return $rawHeaders;
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
        
        if ($this->allowContentEncoding) {
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
     * Does the response utilize chunked transfer-encoding?
     * 
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
        if ('HEAD' == $request->getMethod()) {
            return false;
        }
        return true;
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
     * Is it possible to redirect the current state's response?
     * 
     * ClientRequestState $state
     * @return bool
     */
    protected function canRedirect(ClientRequestState $state) {
        if (!$state->response->hasHeader('Location')) {
            return false;
        }
        
        if (count($state->redirectHistory) >= $this->maxRedirects) {
            return false;
        }
        
        $requestMethod = $state->request->getMethod();
        if (!$this->nonStandardRedirectFlag && !in_array($requestMethod, array('GET', 'HEAD'))) {
            return false;
        }
        
        return true;
    }

    /**
     * Adjust the request state to redirect using the response's Location header
     * 
     * @param ClientRequestState $state
     * @return void
     */
    protected function redirect(ClientRequestState $state) {
        $oldLocation = $state->request->getRawUri();
        $newLocation = $this->normalizeRedirectLocation($state->request, $state->response);
        
        $state->redirectHistory[$oldLocation] = $state->request;
        
        if (isset($state->redirectHistory[$newLocation])) {
            throw new InfiniteRedirectException(
                "Infinite redirect loop detected: cannot redirect to $newLocation"
            );
        }
        
        $newRequest = new StdRequest($newLocation, $state->request->getMethod());
        $newRequest->setAllHeaders($state->request->getAllHeaders());
        if ($newRequest->allowsEntityBody()) {
            $entityBody = $state->request->getBodyStream() ?: $state->request->getBody();
            $newRequest->setBody($entityBody);
        }
        $newRequest->setHttpVersion($state->request->getHttpVersion());
        
        $state->request = $newRequest;
        
        $newResponse = $this->makeClientResponse();
        $newResponse->setPreviousResponse($state->response);
        
        $state->response = $newResponse;
        
        $state->buffer = '';
        $state->bufferSize = 0;
        $state->totalBytesSent = 0;
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
     * Retrieves the initial request in a redirection chain from a request state holder
     * 
     * @param ClientRequestState $state
     * @return Request
     */
    protected function getInitialRequestFromState(ClientRequestState $state) {
        return reset($state->redirectHistory) ?: $state->request;
    }

    /**
     * Correct invalid Location headers that specify relative URI paths
     * 
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
     * Open a writable in-memory stream
     * 
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
     * Close all open connections on object destruction
     * 
     * @return void
     */
    public function __destruct() {
        $this->connMgr->closeAll();
    }
}
