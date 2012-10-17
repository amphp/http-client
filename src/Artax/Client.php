<?php

namespace Artax;

use Exception,
    Traversable,
    SplObjectStorage,
    Spl\Mediator,
    Spl\TypeException,
    Spl\ValueException,
    Artax\Streams\Stream,
    Artax\Streams\Socket,
    Artax\Streams\Resource,
    Artax\Streams\StreamException,
    Artax\Streams\ConnectException,
    Artax\Streams\SocketGoneException,
    Artax\Http\Request,
    Artax\Http\StdRequest,
    Artax\Http\Response;
    
class Client {
    
    const USER_AGENT = 'Artax/1.0.0-rc.1 (PHP5.3+)';
    
    const ATTR_KEEP_CONNS_ALIVE = 'keepConnsAlive';
    const ATTR_CONNECT_TIMEOUT = 'connectTimeout';
    const ATTR_FOLLOW_LOCATION = 'followLocation';
    const ATTR_AUTO_REFERER_ON_FOLLOW = 'autoRefererOnFollow';
    const ATTR_HOST_CONCURRENCY_LIMIT = 'hostConcurrencyLimit';
    const ATTR_IO_BUFFER_SIZE = 'ioBufferSize';
    const ATTR_SSL_VERIFY_PEER = 'sslVerifyPeer';
    const ATTR_SSL_ALLOW_SELF_SIGNED = 'sslAllowSelfSigned';
    const ATTR_SSL_CA_FILE = 'sslCertAuthorityFile';
    const ATTR_SSL_CA_PATH = 'sslCertAuthorityDirPath';
    const ATTR_SSL_LOCAL_CERT = 'sslLocalCertFile';
    const ATTR_SSL_LOCAL_CERT_PASSPHRASE = 'sslLocalCertPassphrase';
    const ATTR_SSL_CN_MATCH = 'sslCommonNameMatch';
    const ATTR_SSL_VERIFY_DEPTH = 'sslVerifyDepth';
    const ATTR_SSL_CIPHERS = 'sslCiphers';
    
    const FOLLOW_LOCATION_NONE = 0;
    const FOLLOW_LOCATION_ON_3XX = 1;
    const FOLLOW_LOCATION_ON_2XX = 2;
    const FOLLOW_LOCATION_ON_UNSAFE_METHOD = 4;
    const FOLLOW_LOCATION_ALL = 7;
    
    const EVENT_REQUEST = 'artax.client.request';
    const EVENT_REDIRECT = 'artax.client.redirect';
    const EVENT_RESPONSE = 'artax.client.response';
    const EVENT_SOCK_OPEN = 'artax.client.socket.open';
    const EVENT_SOCK_CHECKOUT = 'artax.client.socket.checkout';
    const EVENT_SOCK_CHECKIN = 'artax.client.socket.checkin';
    const EVENT_SOCK_CLOSE = 'artax.client.socket.close';
    const EVENT_SOCK_IO_READ = 'artax.client.socket.io.read';
    const EVENT_SOCK_IO_WRITE = 'artax.client.socket.io.write';
    
    const STATE_NEEDS_SOCKET = 0;
    const STATE_SEND_REQUEST_HEADERS = 1;
    const STATE_SEND_BUFFERED_REQUEST_BODY = 2;
    const STATE_SEND_STREAM_REQUEST_BODY = 4;
    const STATE_READ_HEADERS = 8;
    const STATE_READ_TO_SOCKET_CLOSE = 16;
    const STATE_READ_TO_CONTENT_LENGTH = 32;
    const STATE_READ_CHUNKS = 64;
    const STATE_COMPLETE = 128;
    
    /**
     * @var array
     */
    private $attributes = array(
        self::ATTR_KEEP_CONNS_ALIVE => true,
        self::ATTR_CONNECT_TIMEOUT => 60,
        self::ATTR_FOLLOW_LOCATION => self::FOLLOW_LOCATION_ON_3XX,
        self::ATTR_AUTO_REFERER_ON_FOLLOW => true,
        self::ATTR_HOST_CONCURRENCY_LIMIT => 5,
        self::ATTR_IO_BUFFER_SIZE => 8192,
        self::ATTR_SSL_VERIFY_PEER => true,
        self::ATTR_SSL_ALLOW_SELF_SIGNED => false,
        self::ATTR_SSL_CA_FILE => null,
        self::ATTR_SSL_CA_PATH => null,
        self::ATTR_SSL_LOCAL_CERT => '',
        self::ATTR_SSL_LOCAL_CERT_PASSPHRASE => null,
        self::ATTR_SSL_CN_MATCH => null,
        self::ATTR_SSL_VERIFY_DEPTH => 5,
        self::ATTR_SSL_CIPHERS => 'DEFAULT'
    );
    
    /**
     * @var \Spl\Mediator
     */
    private $mediator;
    
    /**
     * @var array
     */
    private $requestKeys;
    
    /**
     * @var array
     */
    private $requests;
    
    /**
     * @var array
     */
    private $responses;
    
    /**
     * @var array
     */
    private $sockets;
    
    /**
     * @var array
     */
    private $errors;
    
    /**
     * @var array
     */
    private $states;
    
    /**
     * @var array
     */
    private $resourceKeyMap;
    
    /**
     * @var array
     */
    private $redirectHistory;
    
    /**
     * @var array
     */
    private $sockPool = array();
    
    /**
     * @var bool
     */
    private $isInMultiMode;
    
    /**
     * @param \Spl\Mediator $mediator
     * @return void
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * Retrieve a Client attribute value
     * 
     * @param string $attribute
     * @throws \Spl\ValueException On non-existent attribute
     * @return mixed Returns the requested attribute's current value
     */
    public function getAttribute($attribute) {
        if (array_key_exists($attribute, $this->attributes)) {
            return $this->attributes[$attribute];
        }
        throw new ValueException(
            'Invalid Client attribute: ' . $attribute . ' does not exist'
        );
    }
    
    /**
     * Assign optional Client attributes
     * 
     * @param int $attribute
     * @param mixed $value
     * @throws \Spl\ValueException On invalid attribute
     * @return void
     */
    public function setAttribute($attribute, $value) {
        if (!array_key_exists($attribute, $this->attributes)) {
            throw new ValueException(
                'Invalid Client attribute: ' . $attribute . ' does not exist'
            );
        }
        
        $setterMethod = 'set' . ucfirst($attribute);
        if (method_exists($this, $setterMethod)) {
            $this->$setterMethod($value);
        } else {
            $this->attributes[$attribute] = $value;
        }
    }
    
    /**
     * Assign multiple Client attributes at once
     * 
     * @param mixed $arrayOrTraversable
     * @return void
     */
    public function setAllAttributes($arrayOrTraversable) {
        foreach ($arrayOrTraversable as $attribute => $value) {
            $this->setAttribute($attribute, $value);
        }
    }
    
    private function setKeepConnsAlive($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_KEEP_CONNS_ALIVE] = $boolFlag;
    }
    
    private function setConnectTimeout($secondsUntilTimeout) {
        $this->attributes[self::ATTR_CONNECT_TIMEOUT] = (int) $secondsUntilTimeout;
    }
    
    private function setFollowLocation($bitmask) {
        $this->attributes[self::ATTR_FOLLOW_LOCATION] = (int) $bitmask;
    }
    
    private function setAutoRefererOnFollow($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_AUTO_REFERER_ON_FOLLOW] = $boolFlag;
    }
    
    private function setHostConcurrencyLimit($maxConnections) {
        $maxConnections = (int) $maxConnections;
        $maxConnections = $maxConnections < 1 ? 1 : $maxConnections;
        $this->attributes[self::ATTR_HOST_CONCURRENCY_LIMIT] = $maxConnections;
    }
    
    private function setIoBufferSize($bytes) {
        $bytes = (int) $bytes;
        if ($bytes > 0) {
            $this->attributes[self::ATTR_IO_BUFFER_SIZE] = $bytes;
        }
    }
    
    private function setSslVerifyPeer($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_SSL_VERIFY_PEER] = $boolFlag;
    }
    
    private function setSslAllowSelfSigned($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_SSL_ALLOW_SELF_SIGNED] = $boolFlag;
    }
    
    private function setSslVerifyDepth($depth) {
        $this->sslVerifyDepth = (int) $depth;
        $this->attributes[self::ATTR_SSL_VERIFY_DEPTH] = $depth;
    }
    
    /**
     * Make an HTTP request
     * 
     * @param Http\Request $request
     * @return ChainableResponse
     * @throws ClientException
     */
    public function send(Request $request) {
        $this->isInMultiMode = false;
        $this->buildRequestMaps(array($request));
        $this->execute();
        
        return $this->responses[0];
    }
    
    /**
     * Make multiple HTTP requests in parallel
     * 
     * @param mixed $requests An array or Traversable list of requests
     * @throws \Spl\TypeException
     * @return ClientMultiResponse
     */
    public function sendMulti($requests) {
        $this->isInMultiMode = true;
        $this->validateRequestList($requests);
        $this->buildRequestMaps($requests);
        $this->execute();
        
        $responsesAndErrors = array();
        foreach ($this->requestKeys as $requestKey) {
            if (isset($this->errors[$requestKey])) {
                $responsesAndErrors[$requestKey] = $this->errors[$requestKey];
            } else {
                $responsesAndErrors[$requestKey] = $this->responses[$requestKey];
            }
        }
        
        return new ClientMultiResponse($responsesAndErrors);
    }
    
    /**
     * Validates the request list passed to Client::sendMulti
     * 
     * @param mixed $requests An array or Traversable object
     * @return void
     * @throws \Spl\TypeException
     */
    private function validateRequestList($requests) {
        if (!($requests instanceof Traversable || is_array($requests))) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new TypeException(
                get_class($this) . '::sendMulti expects an array or Traversable object ' .
                "at Argument 1; $type provided"
            );
        }
        
        if (!count($requests)) {
            throw new TypeException(
                'No requests specified'
            );
        }
        
        foreach ($requests as $request) {
            if (!$request instanceof Request) {
                $type = is_object($request) ? get_class($requests) : gettype($request);
                throw new TypeException(
                    get_class($this) . '::sendMulti requires that each element of the list passed ' .
                    'to Argument 1 implement Artax\\Http\\Request; ' . $type . ' provided'
                );
            }
        }
    }
    
    /**
     * Initializes all request-key maps for this batch of requests.
     * 
     * @param mixed $requests[Request]
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function buildRequestMaps($requests) {
        $this->requestKeys = array();
        
        $this->requests = array();
        $this->responses = array();
        $this->sockets = array();
        $this->errors = array();
        $this->states = array();
        
        $this->resourceKeyMap = array();
        $this->redirectHistory = array();
        
        foreach ($requests as $requestKey => $request) {
            $this->normalizeRequestHeaders($request);
            
            $this->requestKeys[] = $requestKey;
            $this->requests[$requestKey] = $request;
            $this->responses[$requestKey] = new ChainableResponse($request->getUri());
            $this->redirectHistory[$requestKey] = array();
            $this->states[$requestKey] = new ClientState();
            
            $this->mediator->notify(
                self::EVENT_REQUEST,
                $requestKey,
                $request
            );
        }
    }
    
    /**
     * Normalizes requests header values prior to sending to ensure the validity of certain fields.
     * 
     * User-Agent           - Always added
     * Host                 - Added if missing
     * Connection           - Set to "close" if the Client::ATTR_KEEP_CONNS_ALIVE is set to FALSE
     * Accept-Encoding      - Always removed
     * Content-Length       - Set or removed automatically based on the request entity body
     * Transfer-Encoding    - Set or removed automatically based on the request entity body
     * 
     * Additionally, TRACE requests have their entity bodies removed as per RFC2616-Sec9.8:
     * "A TRACE request MUST NOT include an entity."
     * 
     * NOTE: requests may be modified AFTER normalization by attaching listeners to the 
     * Client::EVENT_REQUEST event.
     * 
     * @param Http\Request $request
     * @return void
     */
    private function normalizeRequestHeaders(Request $request) {
        $request->setHeader('User-Agent', self::USER_AGENT);
        $request->setHeader('Host', $request->getAuthority());
        
        if ('TRACE' == $request->getMethod()) {
            $request->setBody(null);
        }
        
        if ($request->getBodyStream()) {
            $request->setHeader('Transfer-Encoding', 'chunked');
            $request->removeHeader('Content-Length');
        } elseif ($entityBody = $request->getBody()) {
            $request->setHeader('Content-Length', strlen($entityBody));
            $request->removeHeader('Transfer-Encoding');
        } else {
            $request->removeHeader('Content-Length');
            $request->removeHeader('Transfer-Encoding');
        }
        
        $request->removeHeader('Accept-Encoding');
        
        if (!$this->getAttribute(self::ATTR_KEEP_CONNS_ALIVE)) {
            $request->setHeader('Connection', 'close');
        }
    }
    
    /**
     * Is this request waiting for a socket connection?
     * 
     * @param string $requestKey
     * @return bool
     */
    protected function needsSocket($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_NEEDS_SOCKET;
    }
    
    /**
     * Are we currently sending this request's raw HTTP message?
     * 
     * @param string $requestKey
     * @return bool
     */
    protected function isWriting($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state && $state->state < self::STATE_READ_HEADERS;
    }
    
    /**
     * Are we currently reading this request's raw HTTP response message?
     * 
     * @param string $requestKey
     * @return bool
     */
    protected function isReading($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state >= self::STATE_READ_HEADERS && $state->state < self::STATE_COMPLETE;
    }
    
    /**
     * Has the HTTP response for this request been fully received?
     * 
     * @param string $requestKey
     * @return bool
     */
    protected function isComplete($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_COMPLETE;
    }
    
    /**
     * Has this request encountered an error?
     * 
     * @param string $requestKey
     * @return bool
     */
    protected function hasError($requestKey) {
        return isset($this->errors[$requestKey]);
    }
    
    /**
     * The primary work method: sends/receives HTTP requests until all requests have completed.
     * 
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function execute() {
        while ($this->getIncompleteRequestKeys()) {
            list($read, $write) = $this->getSelectableStreams();
            
            if (empty($read) && empty($write)) {
                continue;
            }
            
            list($read, $write) = $this->doStreamSelect($read, $write);
            
            foreach ($write as $resource) {
                $requestKey = $this->resourceKeyMap[(int) $resource];
                $this->doMultiSafeWrite($requestKey);
            }
            
            foreach ($read as $resource) {
                $requestKey = $this->resourceKeyMap[(int) $resource];
                $this->doMultiSafeRead($requestKey);
                
                if ($this->isComplete($requestKey)) {
                    $this->completeResponse($requestKey);
                }
            }
        }
    }
    
    /**
     * Retrieve an array of incomplete request keys
     * 
     * @return array
     */
    private function getIncompleteRequestKeys() {
        $incompletes = array();
        foreach ($this->requestKeys as $requestKey) {
            if (!($this->hasError($requestKey) || $this->isComplete($requestKey))) {
                $incompletes[] = $requestKey;
            }
        }
        return $incompletes;
    }
    
    /**
     * Retrieves an array holding lists of readable and writable request sockets.
     * 
     * @throws ClientException (only if not in multi-mode)
     * @return array
     */
    private function getSelectableStreams() {
        $read = array();
        $write = array();
        
        $this->assignRequestSockets();
        
        foreach ($this->getIncompleteRequestKeys() as $requestKey) {
            if ($this->needsSocket($requestKey)) {
                continue;
            } elseif ($this->isWriting($requestKey)) {
                $write[] = $this->sockets[$requestKey]->getResource();
            } elseif ($this->isReading($requestKey)) {
                $read[] = $this->sockets[$requestKey]->getResource();
            }
        }
        
        return array($read, $write);
    }
    
    /**
     * Checks-out and assigns sockets to requests awaiting a connection. Connection failures are
     * caught in multi-request mode but allowed to bubble up otherwise.
     * 
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function assignRequestSockets() {
        foreach ($this->requestKeys as $requestKey) {
            if (!$this->needsSocket($requestKey)) {
                continue;
            } elseif ($socket = $this->doMultiSafeSocketCheckout($requestKey)){
                $this->states[$requestKey]->state = self::STATE_SEND_REQUEST_HEADERS;
                $this->resourceKeyMap[(int) $socket->getResource()] = $requestKey;
                $this->sockets[$requestKey] = $socket;
            }
        }
    }
    
    /**
     * Checks out a request socket. Exceptions are prevented from bubbling up when in
     * multi-request mode.
     * 
     * @param string $requestKey
     * @throws ClientException (only if not in multi-mode)
     * @return mixed Returns Socket or NULL on host concurrency limiting
     */
    protected function doMultiSafeSocketCheckout($requestKey) {
        if ($this->isInMultiMode) {
            try {
                return $this->checkoutSocket($requestKey);
            } catch (ClientException $e) {
                $this->errors[$requestKey] = $e;
            }
        } else {
            return $this->checkoutSocket($requestKey);
        }
    }
    
    /**
     * A test seam for mocking stream_select results
     * 
     * The native stream_select function takes arrays of open stream resources (both readable and 
     * writable) and modifies those arrays by reference to return only those streams on which IO 
     * actions may be taken without blocking. Because the native stream_select modifies the supplied
     * stream  resource arrays by reference, we simplify test mocking by returning an array 
     * containing the read and write arrays of actionable IO streams.
     * 
     * By setting all of our socket streams to non-blocking mode, we're able to utilize stream_select
     * to execute many requests in parallel in an infinite loop. See the relevant manual entry for 
     * the full stream_select reference:
     * 
     * http://us.php.net/manual/en/function.stream-select.php
     * 
     * @param array $read
     * @param array $write
     * @param array $ex
     * @param int $tvsec
     * @param int $tvusec
     * @return array
     */
    protected function doStreamSelect(
        array $read,
        array $write,
        array $ex = null,
        $tvsec = 3,
        $tvusec = 0
    ) {
        @stream_select($read, $write, $ex, $tvsec, $tvusec);
        return array($read, $write);
    }
    
    /**
     * Allows socket IO write errors to bubble up when processing a single request. Exceptions
     * thrown when in multi-request mode are caught and stored for use in a multi-response.
     * 
     * @param string $requestKey
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function doMultiSafeWrite($requestKey) {
        if ($this->isInMultiMode) {
            try {
                $this->write($requestKey);
            } catch (ClientException $e) {
                $this->errors[$requestKey] = $e;
            }
        } else {
            $this->write($requestKey);
        }
    }
    
    /**
     * Delegates IO writes to the appropriate method based on the current request state
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    public function write($requestKey) {
        $currentState = $this->states[$requestKey]->state;
        
        switch ($currentState) {
            case self::STATE_SEND_REQUEST_HEADERS:
                $this->writeRequestHeaders($requestKey);
                break;
            case self::STATE_SEND_BUFFERED_REQUEST_BODY:
                $this->writeBufferedRequestBody($requestKey);
                break;
            case self::STATE_SEND_STREAM_REQUEST_BODY:
                $this->writeStreamingRequestBody($requestKey);
                break;
        }
    }
    
    /**
     * Writes request headers to socket, updating the request state upon completion
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function writeRequestHeaders($requestKey) {
        $state = $this->states[$requestKey];
        $socket = $this->sockets[$requestKey];
        $request = $this->requests[$requestKey];
        
        $data = $request->getRawRequestLineAndHeaders();
        $dataLen = strlen($data);
        $dataToWrite = substr($data, $state->headerBytesSent);
        
        try {
            $bytesWritten = $socket->write($dataToWrite);
        } catch (StreamException $e) {
            throw new ClientException(
                'Socket write failure while sending headers',
                null,
                $e
            );
        }
        
        if ($bytesWritten) {
            $actualDataWritten = substr($data, 0, $bytesWritten);
            $state->headerBytesSent += $bytesWritten;
            $this->mediator->notify(
                self::EVENT_SOCK_IO_WRITE,
                $requestKey,
                $actualDataWritten,
                $bytesWritten
            );
        }
        
        if ($state->headerBytesSent >= $dataLen) {
            if ($request->getBodyStream()) {
                $state->state = self::STATE_SEND_STREAM_REQUEST_BODY;
                $this->initializeOutboundBodyStream($requestKey);
            } elseif ($request->getBody()) {
                $state->state = self::STATE_SEND_BUFFERED_REQUEST_BODY;
            } else {
                $state->state = self::STATE_READ_HEADERS;
            }
        }
    }
    
    /**
     * @param string $requestKey
     * @return void
     */
    private function initializeOutboundBodyStream($requestKey) {
        $state = $this->states[$requestKey];
        $request = $this->requests[$requestKey];
        
        $outboundBodyStream = $request->getBodyStream();
        fseek($outboundBodyStream, 0, SEEK_END);
        $state->streamRequestBodyLength = ftell($outboundBodyStream);
        
        rewind($outboundBodyStream);
    }
    
    /**
     * Writes a buffered request body to the socket, updating the request state upon completion
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function writeBufferedRequestBody($requestKey) {
        $state = $this->states[$requestKey];
        $socket = $this->sockets[$requestKey];
        $request = $this->requests[$requestKey];
        
        $data = $request->getBody();
        $dataLen = strlen($data);
        $dataToWrite = substr($data, $state->bodyBytesSent);
        
        try {
            $bytesWritten = $socket->write($dataToWrite);
        } catch (StreamException $e) {
            throw new ClientException(
                'Socket write failure while sending request body',
                null,
                $e
            );
        }
        
        if ($bytesWritten) {
            $actualDataWritten = substr($data, 0, $bytesWritten);
            $state->bodyBytesSent += $bytesWritten;
            $this->mediator->notify(
                self::EVENT_SOCK_IO_WRITE,
                $requestKey,
                $actualDataWritten,
                $bytesWritten
            );
        }
        
        if ($state->bodyBytesSent >= $dataLen) {
            $state->state = self::STATE_READ_HEADERS;
        }
    }
    
    /**
     * Writes a streaming request body using a chunked Transfer-Encoding.
     * 
     * Chunks of the entity body stream are sized according to the Client::ATTR_IO_BUFFER_SIZE 
     * attribute.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function writeStreamingRequestBody($requestKey) {
        $state = $this->states[$requestKey];
        $request = $this->requests[$requestKey];
        $socket = $this->sockets[$requestKey];
        
        $outboundBodyStream = $request->getBodyStream();
        if ($state->streamRequestBodyChunkPos >= $state->streamRequestBodyChunkLength) {
            fseek($outboundBodyStream, $state->streamRequestBodyPos, SEEK_SET);
            $ioBufferSize = $this->getAttribute(self::ATTR_IO_BUFFER_SIZE);
            $chunk = @fread($outboundBodyStream, $ioBufferSize);
            
            if (false === $chunk) {
                throw new ClientException(
                    "Failed reading request body from $outboundBodyStream"
                );
            }
            
            $rawChunkSize = strlen($chunk);
            
            $state->streamRequestBodyChunk = dechex($rawChunkSize) . "\r\n$chunk\r\n";
            $state->streamRequestBodyChunkPos = 0;
            $state->streamRequestBodyChunkLength = strlen($state->streamRequestBodyChunk);
            $state->streamRequestBodyChunkRawLength = $rawChunkSize;
        }
        
        $dataToWrite = substr($state->streamRequestBodyChunk, $state->streamRequestBodyChunkPos);
        
        try {
            $bytesWritten = $socket->write($dataToWrite);
        } catch (StreamException $e) {
            throw new ClientException(
                '',
                null,
                $e
            );
        }
        
        if ($bytesWritten) {
            $actualDataWritten = substr($dataToWrite, 0, $bytesWritten);
            $state->streamRequestBodyChunkPos += $bytesWritten;
            $this->mediator->notify(
                self::EVENT_SOCK_IO_WRITE,
                $requestKey,
                $actualDataWritten,
                $bytesWritten
            );
        }
        
        // Is there more of this chunk to write?
        if ($state->streamRequestBodyChunkPos < $state->streamRequestBodyChunkLength) {
            return;
        }
        
        $state->streamRequestBodyPos += $state->streamRequestBodyChunkRawLength;
        
        // If we just wrote the last chunk, change the state to "reading"
        if ($state->streamRequestBodyPos >= $state->streamRequestBodyLength
            && $state->streamRequestBodyChunk == "0\r\n\r\n"
        ) {
            $state->state = self::STATE_READ_HEADERS;
        }
    }
    
    /**
     * Allows socket IO read errors to bubble up when processing a single request. Exceptions
     * thrown when in multi-request mode are caught and stored for use in a multi-response.
     * 
     * @param string $requestKey
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function doMultiSafeRead($requestKey) {
        if ($this->isInMultiMode) {
            try {
                $this->read($requestKey);
            } catch (ClientException $e) {
                $this->errors[$requestKey] = $e;
            }
        } else {
            $this->read($requestKey);
        }
    }
    
    /**
     * Delegates IO reads to the appropriate method based on the current request state
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    public function read($requestKey) {
        $state = $this->states[$requestKey];
        
        if ($state->state == self::STATE_READ_HEADERS) {
            $this->readHeaders($requestKey);
        } elseif ($state->state > self::STATE_READ_HEADERS
            && $state->state < self::STATE_COMPLETE
        ) {
            $this->readBody($requestKey);
        }
    }
    
    /**
     * Reads response headers from socket, updating the request state upon completion
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function readHeaders($requestKey) {
        $state = $this->states[$requestKey];
        $socket = $this->sockets[$requestKey];
        
        try {
            $ioBufferSize = $this->getAttribute(self::ATTR_IO_BUFFER_SIZE);
            $readData = $socket->read($ioBufferSize);
        } catch (StreamException $e) {
            throw new ClientException(
                'Socket read failure while retrieving response headers: ' . $state->buffer,
                null,
                $e
            );
        }
        
        // Remove leading line-breaks from the response message as per RFC2616 Section 4.1
        if (!$state->buffer) {
            $readData = ltrim($readData);
        }
        
        $state->buffer .= $readData;
        
        $this->mediator->notify(
            self::EVENT_SOCK_IO_READ,
            $requestKey,
            $readData,
            strlen($readData)
        );
        
        if (!$this->assignRawHeadersToResponse($requestKey)) {
            return;
        } elseif ($this->isResponseBodyAllowed($requestKey)) {
            $this->initializeResponseBodyRetrieval($requestKey);
        } else {
            $state->state = self::STATE_COMPLETE;
        }
    }
    
    /**
     * Assign raw response headers (if fully received) to the request-key's response object
     * 
     * @param string $requestKey
     * @return bool Returns true if headers assigned or FALSE if full headers not yet received
     */
    private function assignRawHeadersToResponse($requestKey) {
        $state = $this->states[$requestKey];
        
        $bodyStartPos = strpos($state->buffer, "\r\n\r\n");
        
        if (false === $bodyStartPos) {
            return false;
        } else {
            $startLineAndHeaders = substr($state->buffer, 0, $bodyStartPos);
            list($startLine, $headers) = explode("\r\n", $startLineAndHeaders, 2);
            $state->buffer = substr($state->buffer, $bodyStartPos + 4);
            
            $response = $this->responses[$requestKey];
            $response->setStartLine($startLine);
            $response->setAllRawHeaders($headers);
            
            return true;
        }
    }
    
    /**
     * Do the relevant request method and response status code allow an entity body?
     * 
     * @param string $requestKey
     * @return bool
     */
    private function isResponseBodyAllowed($requestKey) {
        $request = $this->requests[$requestKey];
        $response = $this->responses[$requestKey];
        
        if ('HEAD' == $request->getMethod()) {
            return false;
        }
        
        $statusCode = $response->getStatusCode();
        if ($statusCode == 204
            || $statusCode == 304
            || ($statusCode >= 100 && $statusCode < 200)
        ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Determine which entity body retrieval mode to use (chunked/content-length/socket-close) and
     * initialize a temporary stream in which to store the response body.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function initializeResponseBodyRetrieval($requestKey) {
        $state = $this->states[$requestKey];
        $response = $this->responses[$requestKey];
        
        $state->responseBody = $this->makeResponseBodyStream();
        
        try {
            $state->responseBody->open();
        } catch (StreamException $e) {
            throw new ClientException(
                'Failed opening temporary response body stream',
                null,
                $e
            );
        }
        
        try {
            $state->responseBody->write($state->buffer);
        } catch (StreamException $e) {
            throw new ClientException(
                'Failed writing '.strlen($state->buffer).' bytes to temporary response body stream',
                null,
                $e
            );
        }
        
        $hasContentLength = $response->hasHeader('Content-Length');
        
        if ($hasContentLength && $this->receivedFullEntityBodyFromHeaderRead($requestKey)) {
            $state->state = self::STATE_COMPLETE;
        } elseif ($hasContentLength) {
            $state->state = self::STATE_READ_TO_CONTENT_LENGTH;
        } elseif ($this->isResponseChunked($response)) {
            $state->state = self::STATE_READ_CHUNKS;
        } else {
            $state->state = self::STATE_READ_TO_SOCKET_CLOSE;
        }
        
        $state->buffer = null;
    }
    
    /**
     * Determine if the full entity body was received from the final response header IO read. This
     * check is only necessary when a response specifies a Content-Length header.
     * 
     * @param string $requestKey
     * @return bool
     */
    private function receivedFullEntityBodyFromHeaderRead($requestKey) {
        $state = $this->states[$requestKey];
        $response = $this->responses[$requestKey];
        
        $bufferLength = strlen($state->buffer);
        $expectedLength = $response->getHeader('Content-Length');
        
        return ($bufferLength >= $expectedLength);
    }
    
    /**
     * Do the response headers indicate a Transfer-Encoding value other than 'identity'?
     * 
     * @param Response $response
     * @return bool
     */
    private function isResponseChunked(Response $response) {
        if (!$response->hasHeader('Transfer-Encoding')) {
            return false;
        }
        
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
        // 
        // "If a Transfer-Encoding header field (section 14.41) is present and has any value other 
        // than "identity", then the transfer-length is defined by use of the "chunked" 
        // transfer-coding (section 3.6), unless the message is terminated by closing the 
        // connection.
        $transferEncoding = $response->getHeader('Transfer-Encoding');
        
        return strcmp($transferEncoding, 'identity');
    }
    
    /**
     * Incrementally read the response body, updating the request state after each read.
     * 
     * The max number of bytes to read in one pass is controlled by the Client::ATTR_IO_BUFFER_SIZE
     * attribute.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function readBody($requestKey) {
        $state = $this->states[$requestKey];
        $socket = $this->sockets[$requestKey];
        
        try {
            $ioBufferSize = $this->getAttribute(self::ATTR_IO_BUFFER_SIZE);
            $readData = $socket->read($ioBufferSize);
        } catch (SocketGoneException $e) {
            if ($state->state == self::STATE_READ_CHUNKS) {
                throw new ClientException(
                    'Socket connection lost before chunked response body fully retrieved'
                );
            } else {
                $state->state = self::STATE_COMPLETE;
                $this->finalizeResponseBodyStream($requestKey);
                return;
            }
        } catch (StreamException $e) {
            throw new ClientException(
                'Socket read failure while retrieving response body',
                null,
                $e
            );
        }
        
        $this->mediator->notify(
            self::EVENT_SOCK_IO_READ,
            $requestKey,
            $readData,
            strlen($readData)
        );
        
        try {
            $state->responseBody->write($readData);
        } catch (StreamException $e) {
            throw new ClientException(
                'Failed writing to temporary response body stream',
                null,
                $e
            );
        }
        
        if ($state->state == self::STATE_READ_TO_CONTENT_LENGTH) {
            $this->markResponseCompleteIfLengthReached($requestKey);
        } elseif ($state->state == self::STATE_READ_CHUNKS) {
            $this->markResponseCompleteIfFinalChunkReceived($requestKey);
        }
        
        if ($state->state == self::STATE_COMPLETE) {
            $this->finalizeResponseBodyStream($requestKey);
        }
    }
    
    /**
     * Mark the response complete if the response body size reaches the expected Content-Length
     * 
     * @param string $requestKey
     * @return void
     */
    private function markResponseCompleteIfLengthReached($requestKey) {
        $state = $this->states[$requestKey];
        $response = $this->responses[$requestKey];
        
        $totalBytesRecd = ftell($state->responseBody->getResource());
        $expectedLength = (int) $response->getHeader('Content-Length');
        
        if ($totalBytesRecd >= $expectedLength) {
            $state->state = self::STATE_COMPLETE;
        }
    }
    
    /**
     * Mark the response complete if the the final response body chunk has been read.
     * 
     * @param string $requestKey
     * @return void
     */
    private function markResponseCompleteIfFinalChunkReceived($requestKey) {
        $state = $this->states[$requestKey];
        
        fseek($state->responseBody->getResource(), -1024, SEEK_END);
        $endOfStream = stream_get_contents($state->responseBody->getResource());
        if (preg_match(",\r\n0+\r\n\r\n$,", $endOfStream)) {
            stream_filter_prepend($state->responseBody->getResource(), 'dechunk');
            $state->state = self::STATE_COMPLETE;
        }
    }
    
    /**
     * Validate the response entity body and assign it to the response object
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function finalizeResponseBodyStream($requestKey) {
        $state = $this->states[$requestKey];
        $response = $this->responses[$requestKey];
        $responseBody = $state->responseBody->getResource();
        
        $this->validateContentLength($response, $responseBody);
        $this->validateContentMd5($response, $responseBody);
        
        rewind($responseBody);
        $response->setBody($responseBody);
    }
    
    /**
     * Verify the received response body against the Content-Length header if applicable
     * 
     * @param Http\Response $response
     * @param resource $responseBody
     * @throws ClientException
     * @return void
     */
    private function validateContentLength(Response $response, $responseBody) {
        if (!$response->hasHeader('Content-Length')) {
            return;
        }
        
        fseek($responseBody, 0, SEEK_END);
        $actualLength = ftell($responseBody);
        $expectedLength = $response->getHeader('Content-Length');
        rewind($responseBody);
        
        if (!($actualLength == $expectedLength)) {
            throw new ClientException(
                'Content-Length mismatch: ' . $expectedLength . ' bytes expected, ' .
                $actualLength . ' bytes received'
            );
        }
    }
    
    /**
     * Verify the received response body against the Content-MD5 header if applicable
     * 
     * @param Http\Response $response
     * @param resource $responseBody
     * @throws ClientException
     * @return void
     */
    private function validateContentMd5(Response $response, $responseBody) {
        if (!$response->hasHeader('Content-MD5')) {
            return;
        }
        
        $ioBufferSize = $this->getAttribute(self::ATTR_IO_BUFFER_SIZE);
        $context = hash_init('md5');
        rewind($responseBody);
        
        while (!feof($responseBody)) {
            hash_update_stream($context, $responseBody, $ioBufferSize);
        }
        
        $expectedMd5 = $response->getHeader('Content-MD5');
        $actualMd5 = hash_final($context);
        
        if ($actualMd5 != $expectedMd5) {
            throw new ClientException(
                'Content-MD5 mismatch: response body checksum verification failure'
            );
        }
    }
    
    /**
     * Check-in/close sockets, perform redirects and notify listeners on response completion
     * 
     * @param string $requestKey
     * @return void
     */
    private function completeResponse($requestKey) {
        if ($this->shouldKeepConnectionAlive($requestKey)) {
            $this->checkinSocket($requestKey);
        } else {
            $this->closeSocket($requestKey);
        }
        
        if ($this->isInMultiMode) {
            try {
                $canRedirect = $this->canRedirect($requestKey);
            } catch (ClientException $e) {
                $this->errors[$requestKey] = $e;
                return;
            }
        } else {
            $canRedirect = $this->canRedirect($requestKey);
        }
        
        if ($canRedirect) {
            $this->doRedirect($requestKey);
        } else {
            $this->mediator->notify(
                self::EVENT_RESPONSE,
                $requestKey,
                $this->responses[$requestKey]
            );
        }
    }
    
    /**
     * Should we keep this connection alive once the response is received?
     * 
     * @param string $requestKey
     * @return bool
     */
    public function shouldKeepConnectionAlive($requestKey) {
        $response = $this->responses[$requestKey];
        
        if (!$this->getAttribute(self::ATTR_KEEP_CONNS_ALIVE)) {
            return false;
        }
        
        $hasConnectionHeader = $response->hasHeader('Connection');
        
        if ($response->hasHeader('Connection')) {
            return !strcmp($response->getHeader('Connection'), 'close');
        }
        
        return true;
    }
    
    /**
     * If this response has a Location header are we capable of following it?
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return bool
     */
    private function canRedirect($requestKey) {
        $request = $this->requests[$requestKey];
        $response = $this->responses[$requestKey];
        
        $followLocation = $this->getAttribute(self::ATTR_FOLLOW_LOCATION);
        
        if ($followLocation == self::FOLLOW_LOCATION_NONE) {
            return false;
        }
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        $statusCode = $response->getStatusCode();
        
        $canFollow3xx = self::FOLLOW_LOCATION_ON_3XX;
        if ($statusCode >= 300 && $statusCode < 400 && !($canFollow3xx & $followLocation)) {
           return false;
        }
        
        $canFollow2xx = self::FOLLOW_LOCATION_ON_2XX;
        if ($statusCode >= 200 && $statusCode < 300 && !($canFollow2xx & $followLocation)) {
           return false;
        }
        
        $requestMethod = $request->getMethod();
        $canFollowUnsafe = self::FOLLOW_LOCATION_ON_UNSAFE_METHOD;
        if (!in_array($requestMethod, array('GET', 'HEAD'))
            && !($canFollowUnsafe & $followLocation)
        ) {
            return false;
        }
        
        $redirectLocation = $this->normalizeRedirectLocation($request, $response);
        if ($redirectLocation == $request->getUri()
            || in_array($requestKey, $this->redirectHistory)
        ) {
            throw new ClientException(
                "Infinite redirect loop detected; cannot redirect to $redirectLocation"
            );
        } else {
            $this->redirectHistory[$requestKey][] = $redirectLocation;
        }
        
        return true;
    }

    /**
     * Fix invalid Location headers that don't use an absolute URI.
     * 
     * @param Http\Request $request
     * @param Http\Response $response
     * @return string
     */
    private function normalizeRedirectLocation(Request $request, Response $response) {
        $locationHeader = $response->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $request->getScheme() . '://' . $request->getAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $response->setHeader('Location', $newLocation);
            $response->addHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
            
            return $newLocation;
        } else {
            return $locationHeader;
        }
    }
    
    /**
     * Generate a new set of request map values so we can follow the response's Location header
     * 
     * @param string $requestKey
     * @return void
     */
    private function doRedirect($requestKey) {
        $request = $this->requests[$requestKey];
        $response = $this->responses[$requestKey];
        
        $newRequest = new StdRequest($response->getHeader('Location'), $request->getMethod());
        $newRequest->setHttpVersion($request->getHttpVersion());
        $newRequest->setAllHeaders($request->getAllHeaders());
        $newRequest->setHeader('Host', $newRequest->getAuthority());
        
        if ($this->getAttribute(self::ATTR_AUTO_REFERER_ON_FOLLOW)) {
            $newRequest->setHeader('Referer', $request->getUri());
        }
        
        $body = $request->getBodyStream() ?: $request->getBody();
        $newRequest->setBody($body);
        
        $newResponse = new ChainableResponse($newRequest->getUri());
        $newResponse->setPreviousResponse($response);
        
        $this->requests[$requestKey] = $newRequest;
        $this->responses[$requestKey] = $newResponse;
        $this->states[$requestKey] = new ClientState();
        
        $this->mediator->notify(
            self::EVENT_REDIRECT,
            $requestKey,
            $request->getUri(),
            $newRequest->getUri()
        );
    }
    
    /**
     * Get a socket for use subject to the Client's host concurrency limit.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return Streams\Socket Returns NULL if max connections already in use for the request host
     */
    private function checkoutSocket($requestKey) {
        $request = $this->requests[$requestKey];
        $socketUri = $this->buildSocketUri($request);
        $sockPoolKey = $socketUri->__toString();
        
        if (!isset($this->sockPool[$sockPoolKey])) {
            $this->sockPool[$sockPoolKey] = new SplObjectStorage();
        } elseif ($socket = $this->checkoutExistingSocket($requestKey, $sockPoolKey)) {
            return $socket;
        } elseif ($this->hostConcurrencyLimitAllowsNewConnection($sockPoolKey)) {
            return $this->checkoutNewSocket($requestKey, $socketUri, $sockPoolKey);
        }
    }
    
    /**
     * Attempt to use an existing Keep-Alive socket connection before making a new connection
     * 
     * @param string $sockPoolKey
     * @return SocketResource Returns SocketResource or NULL if no existing sockets are available
     */
    private function checkoutExistingSocket($requestKey, $sockPoolKey) {
        foreach ($this->sockPool[$sockPoolKey] as $socket) {
            $isInUse = $this->sockPool[$sockPoolKey]->getInfo();
            
            if (!$isInUse) {
                $this->sockPool[$sockPoolKey]->setInfo(true);
                $this->mediator->notify(
                    self::EVENT_SOCK_CHECKOUT,
                    $requestKey,
                    $socket
                );
                return $socket;
            }
        }
        
        return null;
    }
    
    /**
     * Do our host concurrency limit settings allow a new connection to the relevant host?
     * 
     * @param string $sockPoolKey
     * @return bool
     */
    private function hostConcurrencyLimitAllowsNewConnection($sockPoolKey) {
        $currentHostSocks = count($this->sockPool[$sockPoolKey]);
        return $this->getAttribute(self::ATTR_HOST_CONCURRENCY_LIMIT) > $currentHostSocks;
    }
    
    /**
     * Open a new non-blocking socket connection, notifying listeners of the socket's status
     * 
     * @param string $requestKey
     * @param Uri $socketUri
     * @param $sockPoolKey
     * @throws ClientException On connection failure
     * @return Streams\Socket
     */
    private function checkoutNewSocket($requestKey, Uri $socketUri, $sockPoolKey) {
        $request = $this->requests[$requestKey];
        $socket = $this->makeSocket($socketUri);
        $socket->setConnectTimeout($this->getAttribute(self::ATTR_CONNECT_TIMEOUT));
        
        if ('tls' == $socketUri->getScheme()) {
            $socketContext = $this->buildSslContext($socketUri);
            $socket->setContextOptions($socketContext);
        }
        
        try {
            $socket->open();
        } catch (StreamException $e) {
            throw new ClientException(
                "Failed opening socket connection to $socketUri",
                null,
                $e
            );
        }
        
        stream_set_blocking($socket->getResource(), 0);
        
        $this->mediator->notify(
            self::EVENT_SOCK_OPEN,
            $requestKey,
            $socket
        );
        
        $this->sockPool[$sockPoolKey]->attach($socket, true);
        
        $this->mediator->notify(
            self::EVENT_SOCK_CHECKOUT,
            $requestKey,
            $socket
        );
        
        return $socket;
    }
    
    /**
     * Generate the appropriate socket URI given a Request instance
     * 
     * @param Http\Request $request
     * @throws ClientException
     * @return Uri
     */
    private function buildSocketUri(Request $request) {
        $requestScheme = strtolower($request->getScheme());
        switch ($requestScheme) {
            case 'http':
                $scheme = 'tcp';
                break;
            case 'https':
                $scheme = 'tls';
                break;
            default:
                throw new ClientException(
                    "Invalid request URI scheme: $requestScheme. http:// or https:// required."
                );
        }
        
        $uriStr = $scheme . '://' . $request->getHost() . ':' . $request->getPort();
        $uri = new Uri($uriStr);
        
        return $uri;
    }
    
    /**
     * IMPORTANT: this is a test seam allowing us to mock the sockets in our HTTP transfers.
     * 
     * @param string $socketUri
     * @return Streams\Socket
     */
    protected function makeSocket($socketUri) {
        return new Socket($socketUri);
    }
    
    /**
     * IMPORTANT: this is a test seam allowing us to mock local stream storage of response bodies.
     * 
     * @return Streams\Stream
     */
    protected function makeResponseBodyStream() {
        return new Stream('php://temp', 'r+');
    }

    /**
     * Build context options for TLS connections according to our Client SSL attributes
     * 
     * @param Uri $socketUri
     * @return array
     */
    private function buildSslContext(Uri $socketUri) {
        $opts = array(
            'verify_peer' => $this->getAttribute(self::ATTR_SSL_VERIFY_PEER),
            'allow_self_signed' => $this->getAttribute(self::ATTR_SSL_ALLOW_SELF_SIGNED),
            'verify_depth' => $this->getAttribute(self::ATTR_SSL_VERIFY_DEPTH),
            'cafile' => $this->getAttribute(self::ATTR_SSL_CA_FILE),
            'CN_match' => $this->getAttribute(self::ATTR_SSL_CN_MATCH) ?: $socketUri->getHost(),
            'ciphers' => $this->getAttribute(self::ATTR_SSL_CIPHERS)
        );
        
        if ($caDirPath = $this->getAttribute(self::ATTR_SSL_CA_PATH)) {
            $opts['capath'] = $caDirPath;
        }
        if ($localCert = $this->getAttribute(self::ATTR_SSL_LOCAL_CERT)) {
            $opts['local_cert'] = $localCert;
        }
        if ($localCertPassphrase = $this->getAttribute(self::ATTR_SSL_LOCAL_CERT_PASSPHRASE)) {
            $opts['passphrase'] = $localCertPassphrase;
        }
       
        return array('ssl' => $opts);
    }
    
    /**
     * Mark a socket connection as "available for use" by future or queued requests
     * 
     * @param Streams\Socket $socket
     * @return void
     */
    private function checkinSocket($requestKey) {
        $socket = $this->sockets[$requestKey];
        $sockPoolKey = $socket->getUri();
        $this->sockPool[$sockPoolKey]->attach($socket, false);
        
        unset($this->resourceKeyMap[(int)$socket->getResource()]);
        
        $this->mediator->notify(
            self::EVENT_SOCK_CHECKIN,
            $requestKey,
            $socket
        );
    }
    
    /**
     * Close a socket connection
     * 
     * @param Streams\Socket $socket
     * @return void
     */
    private function closeSocket($requestKey) {
        $socket = $this->sockets[$requestKey];
        $sockPoolKey = $socket->getUri();
        $this->sockPool[$sockPoolKey]->detach($socket);
        
        unset($this->resourceKeyMap[(int)$socket->getResource()]);
        
        $socket->close();
        
        $this->mediator->notify(
            self::EVENT_SOCK_CLOSE,
            $requestKey,
            $socket
        );
    }
    
    /**
     * Close all open socket streams
     * 
     * @return int Returns the number of socket connections closed
     */
    public function closeAllSockets() {
        $connsClosed = 0;
        
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $socket) {
                $socket->close();
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
    public function closeSocketsByHost($host) {
        $connsClosed = 0;
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $socket) {
                if ($socket->getHost() == $host) {
                    $socket->close();
                    ++$connsClosed;
                }
            }
        }
        
        return $connsClosed;
    }
    
    /**
     * Close all socket streams that have been idle longer than the specified number of seconds
     * 
     * @param int $maxInactivitySeconds
     * @return int Returns the number of socket connections closed
     */
    public function closeIdleSockets($maxInactivitySeconds) {
        $maxInactivitySeconds = (int) $maxInactivitySeconds;
        $connectionsClosed = 0;
        $now = time();
        
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $socket) {
                if ($now - $socket->getActivityTimestamp() > $maxInactivitySeconds) {
                    $socket->close();
                    ++$connectionsClosed;
                }
            }
        }
        
        return $connectionsClosed;
    }
}