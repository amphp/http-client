<?php

namespace Artax\Http;

use Spl\Mediator,
    StdClass,
    Traversable,
    Exception,
    RuntimeException,
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
    
    const EVENT_CONNECT_OPEN = 'artax.http.client.connect.open';
    const EVENT_CONNECT_CLOSE = 'artax.http.client.connect.close';
    const EVENT_CONNECT_ERROR = 'artax.http.client.connect.error';
    const EVENT_IO_READ = 'artax.http.client.io.read';
    const EVENT_IO_WRITE = 'artax.http.client.io.write';
    const EVENT_IO_TIMEOUT = 'artax.http.client.io.timeout';
    const EVENT_IO_ERROR = 'artax.http.client.io.error';
    const EVENT_REDIRECT = 'artax.http.client.redirect';
    const EVENT_RESPONSE_COMPLETE = 'artax.http.client.response.complete';
    
    /**
     * @var string
     */
    protected $userAgent = 'Artax-Http/0.1 (PHP5.3+)';
    
    /**
     * @var int
     */
    protected $connectTimeout = 120;
    
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
     * @var int
     */
    protected $hostConcurrencyLimit = 5;
    
    /**
     * @var array
     */
    protected $sslOptions = array();
    
    /**
     * @var string
     */
    protected $acceptedEncodings = 'identity';
    
    /**
     * @var array
     */
    protected $connectionPool = array();
    
    /**
     * @var Spl\Mediator
     */
    protected $mediator;
    
    /**
     * @param Mediator $mediator
     * @return void
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * Request an HTTP resource and retrieve the response
     * 
     * @param Request $request
     * @return Response
     */
    public function send(Request $request) {
        $completedRequestStates = $this->doStreamSelect(array($request));
        return $completedRequestStates[0]->response;
    }
    
    /**
     * Retrieve multiple HTTP resources in parallel
     * 
     * Responses are returned in an array with the same "keys" as the original request list. If an
     * error occurs during the retrieval of one resource, the relevant exception will be returned
     * in place of a Response object for that key.
     * 
     * @param mixed $requests An array, StdClass or Traversable object
     * @return array
     * @throws InvalidArgumentException
     */
    public function sendMulti($requests) {
        $this->validateMultiRequestTraversable($requests);
        $completedRequestStates = $this->doStreamSelect($requests);
        $responses = array_map(function($s) { return $s->response; }, $completedRequestStates);
        
        return $responses;
    }
    
    /**
     * @param mixed $requests An array, StdClass or Traversable object
     * @return void
     * @throws InvalidArgumentException
     */
    private function validateMultiRequestTraversable($requests) {
        if (!(is_array($requests)
            || $requests instanceof Traversable
            || $requests instanceof StdClass
        )) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new InvalidArgumentException(
                "Client::sendMulti expects an array, StdClass or Traversable object at Argument " .
                "1; $type provided"
            );
        }
        
        foreach ($requests as $request) {
            if ($request instanceof Request) {
                continue;
            }
            $type = is_object($request) ? get_class($requests) : gettype($request);
            throw new InvalidArgumentException(
                "Client::sendMulti requires that each element of the list passed to Argument " .
                "1 implement Artax\\Http\\Request; $type provided"
            );
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
        $state = new RequestState();
        
        $state->request = $this->normalizeRequestHeaders($request);
        
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();
        
        $state->conn = $this->makeConnection($scheme, $host, $port);
        $state->conn->setConnectFlags(STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        $this->doConnect($state->conn);
        
        while ($state->status < self::STATE_READING_RESPONSE_HEADERS) {
            $this->sendRequest($state);
        }
        
        $this->closeConnection($state->conn);
    }

    /**
     * Set the number of seconds before a socket connection attempt times out.
     * 
     * @param int $seconds
     * @return void
     */
    public function setConnectTimout($seconds) {
        $this->connectTimeout = (int) $seconds;
    }
    
    /**
     * Set the number of seconds between reads/writes before an IO timeout occurs on a connection
     * 
     * @param int $seconds
     * @return void
     */
    public function setActivityTimeout($seconds) {
        $this->activityTimeout = $seconds;
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
        $this->sslOptions = $options;
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
     * Set the maximum number of sumultaneous connections allowed per host
     * 
     * The default value is 5. If the maximum number of simultaneous connections to a specific host
     * are already in use during a `Client::sendMulti` operation, further requests to that host are
     * queued until one of the existing in-use connections to that host becomes available.
     * 
     * Note: asynchronous requests are not subject to the concurrency limit because they always 
     * open a new request in asynchronous mode and close the connection immediately after a request
     * is sent.
     * 
     * @param int $maxConnections
     * @return void
     */
    public function setHostConcurrencyLimit($maxConnections) {
        $maxConnections = (int) $maxConnections;
        if ($maxConnections < 1) {
            $maxConnections = 1;
        }
        
        $this->hostConcurrencyLimit = $maxConnections;
    }
    
    /**
     * Dispatch state holders to the appropriate machine as reads/writes become available
     * 
     * @param mixed $requests A traversable list of Request objects
     */
    protected function doStreamSelect($requests) {
        list($requestStates, $requestQueue) = $this->buildStreamSelectStateMap($requests);
        $readArr = $writeArr = $this->buildStreamSelectArray($requestStates);
        
        $ex = null;
        
        while (true) {
            
            if ($this->allRequestStatesHaveCompleted($requestStates)) {
                return $requestStates;
            }
            
            if (false === stream_select($readArr, $writeArr, $ex, 1)) {
                continue;
            }
            
            foreach ($writeArr as $stateKey => $stream) {
                $state = $requestStates[$stateKey];
                $state->lastActivity = microtime(true);
                try {
                    $this->sendRequest($state);
                } catch (Exception $e) {
                    $state->status = self::STATE_ERROR;
                    $state->response = $e;
                }
            }
            
            foreach ($readArr as $stateKey => $stream) {
                $state = $requestStates[$stateKey];
                $state->lastActivity = microtime(true);
                try {
                    $this->readResponse($state);
                } catch (Exception $e) {
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
            
            foreach ($requestStates as $stateKey => $state) {
                if ($this->isComplete($state)) {
                    continue;
                } elseif ($this->hasExceededActivityTimeOut($state)) {
                    $this->handleActivityTimeout($state);
                } elseif ($state->status < self::STATE_READING_RESPONSE_HEADERS) {
                    $writeArr[$stateKey] = $state->conn->getStream();
                } elseif ($state->status < self::STATE_RESPONSE_COMPLETE) {
                    $readArr[$stateKey] = $state->conn->getStream();
                }
            }
        }
    }
    
    /**
     * 
     */
    protected function hasExceededActivityTimeOut(RequestState $state) {
        $now = microtime(true);
        $diff = $now - $state->lastActivity;
        return $diff > $this->activityTimeout;
    }
    
    /**
     * 
     */
    protected function handleActivityTimeout(RequestState $state) {
        $this->closeConnection($state->conn);
        $state->status = self::STATE_ERROR;
        $msg = "Inactivity timeout exceeded on connection to {$state->conn}";
        $this->mediator->notify(self::EVENT_IO_TIMEOUT, $msg);
        $state->response = new TimeoutException($msg);
    }
    
    /**
     * Determines if retrieval for all states in the array has completed
     * 
     * @param array $requestStates
     * @return bool
     */
    protected function allRequestStatesHaveCompleted(array $requestStates) {
        $complete = array_map(array($this, 'isComplete'), $requestStates);
        return array_sum($complete) == count($requestStates);
    }
    
    /**
     * Is retrieval complete for the specified state?
     * 
     * @return bool
     */
    protected function isComplete(RequestState $state) {
        return $state->status >= self::STATE_RESPONSE_COMPLETE;
    }
    
    /**
     * A state machine to send raw HTTP requests to a socket stream
     * 
     * @param RequestState $state
     * @return void
     */
    protected function sendRequest(RequestState $state) {
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
     * @param RequestState $state
     * @return void
     */
    protected function writeRequestHeaders(RequestState $state) {
        $rawHeaders = $this->buildRawRequestHeaders($state->request);
        $totalBytesToWrite = strlen($rawHeaders);
        
        if ($state->totalBytesSent < $totalBytesToWrite) {
            $dataToSend = substr($rawHeaders, $state->totalBytesSent);
            
            if ($bytesSent = $this->writeDataToStream($state->conn->getStream(), $dataToSend)) {
                $state->totalBytesSent += $bytesSent;
                $actualDataSent = substr($dataToSend, 0, $bytesSent);
                $this->mediator->notify(self::EVENT_IO_WRITE, $actualDataSent);
                
            } elseif (false === $bytesSent) {
            
                $failedWriteLength = strlen($dataToSend);
                $msg = "Failed writing $failedWriteLength bytes to " . $state->conn->getAuthority();
                $this->mediator->notify(self::EVENT_IO_ERROR, $msg);
                throw new TransferException($msg);
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
     * @param RequestState $state
     * @return void
     */
    protected function writeBufferedRequestBody(RequestState $state) {
        $entityBody = $state->request->getBody();
        $totalBytesToWrite = strlen($entityBody);
        
        if ($state->totalBytesSent < $totalBytesToWrite) {
            $dataToSend = substr($entityBody, $state->totalBytesSent);
            
            if ($bytesSent = $this->writeDataToStream($state->conn->getStream(), $dataToSend)) {
                $state->totalBytesSent += $bytesSent;
                $actualDataSent = substr($dataToSend, 0, $bytesSent);
                $this->mediator->notify(self::EVENT_IO_WRITE, $actualDataSent);
            } elseif (false === $bytesSent) {
                $failedWriteLength = strlen($dataToSend);
                $msg = "Failed writing $failedWriteLength bytes to " . $state->conn->getAuthority();
                $this->mediator->notify(self::EVENT_IO_ERROR, $msg);
                throw new TransferException($msg);
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
     * @param RequestState $state
     * @return void
     */
    protected function writeStreamingRequestBody(RequestState $state) {
        
        $entityBody = $state->request->getBodyStream();
        
        while (true) {
            
            if (!$state->requestBodyStreamBuffer) {
                if (false === ($data = @fread($entityBody, $this->chunkSize))) {
                    throw new RuntimeException(
                        "Failed reading data from request body $entityBody"
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
            
            if ($bytesSent = $this->writeDataToStream(
                $state->conn->getStream(),
                $state->requestBodyStreamBuffer
            )) {
                $state->requestBodyStreamPos += $bytesSent;
                
                if ($state->requestBodyStreamPos == $state->requestBodyStreamLength) {
                    $state->totalBytesSent += $chunkLength;
                    $state->requestBodyStreamBuffer = null;
                }
                
                if ($state->totalBytesSent == ftell($entityBody)) {
                    $this->writeDataToStream($state->conn->getStream(), "0\r\n\r\n");
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
     * @param RequestState $state
     * @return void
     */
    protected function readResponse(RequestState $state) {
        
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
        
        rewind($state->responseBodyStream);
        $entityBody = stream_get_contents($state->responseBodyStream);
        $state->response->setBody($entityBody);
        
        
        if ($state->response->hasHeader('Content-Encoding')) {
            $encoding = strtolower($state->response->getHeader('Content-Encoding'));
            $this->decodeEntityBody($encoding, $state->response->getBody());
        }
        
        if ($this->shouldResponseCloseConnection($state->response)) {
            $this->closeConnection($state->conn);
        } else {
            $state->conn->setInUseFlag(false);
        }
        
        if ($this->canRedirect($state)) {
            $this->redirect($state);
        } else {
            $this->mediator->notify(self::EVENT_RESPONSE_COMPLETE, $state->response);
        }
    }
    
    /**
     * Receives HTTP message headers, returning true if header retrieval is complete
     * 
     * RequestState $state
     * @return bool
     */
    protected function receiveHeaders(RequestState $state) {
        while ($line = $this->readLineFromStream($state->conn->getStream())) {
            $state->buffer .= $line;
            $bytesRecieved = strlen($line);
            $state->responseHeaderBytes += $bytesRecieved;
            $state->responseTotalBytes += $bytesRecieved;
            
            $this->mediator->notify(self::EVENT_IO_READ, $line);
            
            if (substr($state->buffer, -4) !== "\r\n\r\n") {
                continue;
            }
                
            list($startLine, $headers) = explode("\r\n", $state->buffer, 2);
            
            $state->response->setStartLine($startLine);
            $state->response->setAllRawHeaders($headers);
            
            if (!$this->allowsEntityBody($state->response)) {
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
            $msg = "{$state->responseTotalBytes} response bytes received before transfer failure";
            $this->mediator->notify(self::EVENT_IO_ERROR, $msg);
            throw new TransferException($msg);
        }
        
        return false;
    }
    
    /**
     * Receives HTTP message entity-body, returning true if body retrieval complete
     * 
     * RequestState $state
     * @return bool
     */
    protected function receiveBody(RequestState $state) {
        $connectionStream = $state->conn->getStream();
        
        while ($readData = $this->readBytesFromStream($connectionStream, $this->chunkSize)) {
            $dataBytes = strlen($readData);
            fwrite($state->responseBodyStream, $readData);
            $state->responseTotalBytes += $dataBytes;
            $this->mediator->notify(self::EVENT_IO_READ, $readData);
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
            $msg = "{$state->responseTotalBytes} response bytes received before transfer failure";
            $this->mediator->notify(self::EVENT_IO_ERROR, $msg);
            throw new TransferException($msg);
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
            $state = new RequestState();
            $state->request = $this->normalizeRequestHeaders($request);
            
            try {
                $connection = $this->checkoutConnection($state->request);
            } catch (ConnectException $e) {
                $state->status = self::STATE_ERROR;
                $state->response = $e;
                $requestStates[$stateKey] = $state;
                continue;
            }
            
            if (!$connection) {
                $requestStates[$stateKey] = null;
                $requestQueue[$stateKey] = $request;
                continue;
            } else {
                $state->status = self::STATE_SENDING_REQUEST_HEADERS;
                $state->conn = $connection;
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
     * Whether or not a Response should result in a closed connection
     * 
     * "... unless otherwise indicated, the client SHOULD assume that the server will maintain "
     * a persistent connection"
     * 
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec8.html#sec8.1.2
     * 
     * @param Response $response
     * @return bool
     */
    protected function shouldResponseCloseConnection(Response $response) {
        if (!$response->hasHeader('Connection')) {
            return false;
        }
        
        return !strcmp($response->getHeader('Connection'), 'close');
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
     * Is the response allowed to have an entity body?
     * 
     * @param Response $response
     * @return bool
     */
    protected function allowsEntityBody(Response $response) {
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
     * Checkout a new connection subject to the host concurrency limit
     * 
     * @param Request $request
     * 
     * @return ClientConnection -or- null if no connection slots are available
     */
    protected function checkoutConnection(Request $request) {
        $host = $request->getHost();
        $port = $request->getPort();
        $authority = "$host:$port";
        
        $openAuthorityConnections = 0;
        
        if (!isset($this->connectionPool[$authority])) {
            $this->connectionPool[$authority] = array();
        }
        
        foreach ($this->connectionPool[$authority] as $key => $connection) {
            if (!$connection->isConnected()) {
                unset($this->connectionPool[$authority][$key]);
            } else {
                ++$openAuthorityConnections;
            }
            if (!$connection->isInUse()) {
                $connection->setInUseFlag(true);
                return $connection;
            }
        }
        
        if ($this->hostConcurrencyLimit > $openAuthorityConnections) {
        
            $connection = $this->makeConnection($request->getScheme(), $host, $port);
            $connection->setInUseFlag(true);
            $this->doConnect($connection);
            $this->connectionPool[$authority][] = $connection;
            
            return $connection;
        }
        
        return null;
    }
    
    /**
     * Create a new connection with no regard for the host concurrency limit
     * 
     * @param string $scheme
     * @param string $host
     * @param int $port
     * 
     * @return ClientConnection
     */
    protected function makeConnection($scheme, $host, $port) {
        if (strcmp('https', $scheme)) {
            $conn = new TcpConnection("$host:$port");
        } else {
            $conn = new SslConnection("$host:$port");
            $conn->setSslOptions($this->sslOptions);
        }
        
        $conn->setConnectTimeout($this->connectTimeout);
        
        return $conn;
    }
    
    protected function doConnect(StreamConnection $conn) {
        try {
            $conn->connect();
        } catch (ConnectException $e) {
            $this->mediator->notify(self::EVENT_CONNECT_ERROR, 'Connection FAILURE: ' . $e->getMessage());
            throw $e;
        }
        
        $this->mediator->notify(self::EVENT_CONNECT_OPEN, "Connection OPENED: $conn");
    }
    
    /**
     * Close the specified connection and send notification of the event
     * 
     * @param StreamConnection $conn
     * @return void
     */
    protected function closeConnection(StreamConnection $conn) {
        $conn->close();
        $authority = $conn->getAuthority();
        
        if (!empty($this->connectionPool[$authority])
         && false !== ($key = array_search($conn, $this->connectionPool[$authority]))
        ) {
            unset($this->connectionPool[$authority][$key]);
        }
        
        $this->mediator->notify(self::EVENT_CONNECT_CLOSE, "Connection CLOSED: $conn");
    }

    /**
     * Is it possible to redirect the current state's response?
     * 
     * RequestState $state
     * @return bool
     */
    protected function canRedirect(RequestState $state) {
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
     * @param RequestState $state
     * @return void
     */
    protected function redirect(RequestState $state) {
        $oldLocation = $state->request->getRawUri();
        $newLocation = $this->normalizeRedirectLocation($state->request, $state->response);
        
        $state->redirectHistory[] = $oldLocation;
        
        if (in_array($newLocation, $state->redirectHistory)) {
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
        
        $this->mediator->notify(self::EVENT_REDIRECT, "REDIRECTING $oldLocation ---> $newLocation");
        
        $state->conn = $this->checkoutConnection($state->request);
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
    
    protected function writeDataToStream($resource, $data) {
        return @fwrite($resource, $data);
    }
    
    protected function readBytesFromStream($resource, $bytes) {
        return @fread($resource, $bytes);
    }
    
    protected function readLineFromStream($resource) {
        return @fgets($resource);
    }
    
    /**
     * Close any open connections (with notification) on object destruction
     * 
     * @return void
     */
    public function __destruct() {
        foreach ($this->connectionPool as $authority => $connArr) {
            foreach ($connArr as $conn) {
                $this->closeConnection($conn);
            }
        }
    }
}
