<?php

namespace Artax;

use Traversable,
    Spl\TypeException,
    Spl\ValueException,
    Spl\DomainException,
    Artax\Http\Request,
    Artax\Http\StdRequest,
    Artax\Http\Response,
    Artax\Http\ChainableResponse;

/**
 * Retrieves HTTP resources individually or in-parallel
 */
class Client {
    
    const USER_AGENT = 'Artax/1.0.0 (PHP5.3+)';
    
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
     * @var array
     */
    protected $requestKeys;
    
    /**
     * @var array
     */
    protected $requestUris;
    
    /**
     * @var array
     */
    protected $requests;
    
    /**
     * @var array
     */
    protected $responses;
    
    /**
     * @var array
     */
    protected $errors;
    
    /**
     * @var array
     */
    protected $states;
    
    /**
     * @var array
     */
    public $socketPool = array();
    
    /**
     * @var array
     */
    protected $socketIdRequestMap;
    
    /**
     * @var array
     */
    protected $socketIdRequestKeyMap;
    
    /**
     * @var array
     */
    protected $requestKeySocketMap;
    
    /**
     * When TRUE, ClientExceptions are caught to avoid bringing down all requests in the
     * multi-request group.
     * 
     * @var bool
     */
    protected $isInMultiMode;
    
    /**
     * @var array
     */
    protected $requestStatistics;
    
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
     * @param mixed $arrayOrTraversable A key-value traversable list of attributes
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
        $this->attributes[self::ATTR_SSL_VERIFY_DEPTH] = (int) $depth;
    }
    
    /**
     * Make an individual HTTP request
     * 
     * @param Http\Request $request An instance of `Artax\Http\Request` interface
     * @return ChainableResponse The final HTTP response, including any redirects that occurred
     * @throws ClientException On connection failure, socket error or invalid response
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
     * @param mixed $requests An array or Traversable list of request instances
     * @throws \Spl\TypeException On invalid or empty request list
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
     * Initializes all $requestKey maps for this batch of requests.
     * 
     * @param mixed $requests[Request]
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    protected function buildRequestMaps($requests) {
        $this->requestKeys = array();
        $this->requestUris = array();
        $this->requests = array();
        $this->responses = array();
        $this->requestKeySocketMap = array();
        $this->errors = array();
        $this->states = array();
        
        $this->socketIdRequestKeyMap = array();
        
        foreach ($requests as $requestKey => $request) {
            /**
             * @var \Artax\Http\Request $request
             */
            $this->normalizeRequestHeaders($request);
            
            try {
                $uri = $this->buildUri($request->getUri());
                $this->requestUris[$requestKey] = $uri;
                $request->setHeader('Host', $uri->getAuthority());
            } catch (ValueException $e) {
                $uriException = new ClientException(
                    'Invalid Request URI: ' . $request->getUri(),
                    0,
                    $e
                );
                if ($this->isInMultiMode) {
                    $this->errors[$requestKey];
                } else {
                    throw $uriException;
                }
            }
            
            $this->requestKeys[] = $requestKey;
            $this->requests[$requestKey] = $request;
            $this->responses[$requestKey] = new ChainableResponse($request->getUri());
            $this->states[$requestKey] = new ClientState();
        }
    }
    
    private function buildUri($requestUri) {
        $uri = new Uri($requestUri);
        
        $scheme = $uri->getScheme();
        if (!($scheme == 'http' || $scheme == 'https')) {
            throw new ValueException(
                "Invalid URI scheme; [http|https] required"
            );
        }
        
        if ($uri->getHost() == '') {
            throw new ValueException(
                "Empty Request URI host not allowed"
            );
        }
        
        return $uri;
    }
    
    /**
     * Normalizes request header values prior to sending to ensure validity
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
     * @param Http\Request $request
     * @return void
     */
    private function normalizeRequestHeaders(Request $request) {
        $request->setHeader('User-Agent', self::USER_AGENT);
        
        if (Request::TRACE == $request->getMethod()) {
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
    
    private function needsSocket($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_NEEDS_SOCKET;
    }
    
    private function isWriting($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state && $state->state < self::STATE_READ_HEADERS;
    }
    
    private function isReading($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state >= self::STATE_READ_HEADERS && $state->state < self::STATE_COMPLETE;
    }
    
    private function isReadingHeaders($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_READ_HEADERS;
    }
    
    private function isReadingBody($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state > self::STATE_READ_HEADERS && $state->state < self::STATE_COMPLETE;
    }
    
    private function isReadingToContentLength($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_READ_TO_CONTENT_LENGTH;
    }
    
    private function isReadingChunks($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_READ_CHUNKS;
    }
    
    protected function isComplete($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state == self::STATE_COMPLETE;
    }
    
    private function markComplete($requestKey) {
        $state = $this->states[$requestKey];
        return $state->state = self::STATE_COMPLETE;
    }
    
    private function hasError($requestKey) {
        return isset($this->errors[$requestKey]);
    }
    
    /**
     * The primary work method: sends/receives HTTP requests until all requests have completed.
     * 
     * @throws ClientException (only if not in multi-mode)
     * @return void
     */
    private function execute() {
        while ($incompleteRequestKeys = $this->getIncompleteRequestKeys()) {
            $this->assignRequestSockets();
            
            list($read, $write) = $this->getSelectableStreams($incompleteRequestKeys);
            if (empty($read) && empty($write)) {
                continue;
            }
            
            list($read, $write) = $this->doStreamSelect($read, $write);
            
            foreach ($write as $socket) {
                $requestKey = $this->socketIdRequestKeyMap[(int) $socket];
                $this->doMultiSafeWrite($requestKey);
            }
            
            foreach ($read as $socket) {
                $requestKey = $this->socketIdRequestKeyMap[(int) $socket];
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
        $incompleteRequestKeys = array();
        foreach ($this->requestKeys as $requestKey) {
            if (!($this->hasError($requestKey) || $this->isComplete($requestKey))) {
                $incompleteRequestKeys[] = $requestKey;
            }
        }
        return $incompleteRequestKeys;
    }
    
    /**
     * Retrieves an array holding lists of readable and writable request sockets.
     * 
     * @param array<string> $incompleteRequestKeys
     * @throws ClientException (only if not in multi-mode)
     * @return array
     */
    private function getSelectableStreams($incompleteRequestKeys) {
        $read = array();
        $write = array();
        
        foreach ($incompleteRequestKeys as $requestKey) {
            if ($this->needsSocket($requestKey)) {
                continue;
            } elseif ($this->isWriting($requestKey)) {
                $write[] = $this->requestKeySocketMap[$requestKey];
            } elseif ($this->isReading($requestKey)) {
                $read[] = $this->requestKeySocketMap[$requestKey];
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
            }
            
            if (!$socket = $this->doMultiSafeSocketCheckout($requestKey)) {
                continue;
            }
            
            $socketId = (int) $socket;
            
            $this->states[$requestKey]->state = self::STATE_SEND_REQUEST_HEADERS;
            $this->socketIdRequestKeyMap[$socketId] = $requestKey;
            $this->requestKeySocketMap[$requestKey] = $socket;
            
            $this->requestStatistics[$requestKey] = array(
                'connectedAt' => microtime(true),
                'lastSentAt' => null,
                'lastRecdAt' => null,
                'bytesSent' => 0,
                'bytesRecd' => 0,
                'avgUpKbPerSecond' => null,
                'avgDownKbPerSecond' => null
            );
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
    private function doMultiSafeSocketCheckout($requestKey) {
        if ($this->isInMultiMode) {
            try {
                return $this->checkoutSocket($requestKey);
            } catch (ClientException $e) {
                $this->errors[$requestKey] = $e;
                return null;
            }
        } else {
            return $this->checkoutSocket($requestKey);
        }
    }
    
    /**
     * Get a socket for use subject to the Client's host concurrency limit.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return resource Returns socket stream or NULL if limited by host concurrency attribute
     */
    private function checkoutSocket($requestKey) {
        /**
         * @var Uri $requestUri
         */
        $requestUri = $this->requestUris[$requestKey];
        
        $scheme = ($requestUri->getScheme() == 'http') ? 'tcp' : 'tls';
        $host = $requestUri->getHost();
        if (!$port = $requestUri->getPort()) {
            $port = ($scheme == 'tcp') ? 80 : 443;
        }
        
        $authority = "$host:$port";
        $socketUri = new Uri("$scheme://$authority");
        
        if ($socket = $this->doExistingSocketCheckout($authority)) {
            return $socket;
        } elseif ($this->isNewSocketConnectionAllowed($authority)) {
            $socket = $this->doNewSocketCheckout($socketUri);
            $socketId = (int) $socket;
            
            $this->socketPool[$socketId] = array(
                'socket' => $socket,
                'authority' => $authority,
                'bytesSent' => 0,
                'bytesRecd' => 0,
                'activityAt' => microtime(true)
            );
            
            return $socket;
        }
        
        return null;
    }
    
    /**
     * Attempt to use an existing socket connection before making a new connection
     *
     * @param string $socketAuthority
     * @return resource Returns socket stream or NULL if no existing sockets are available for use
     */
    private function doExistingSocketCheckout($socketAuthority) {
        
        foreach ($this->socketPool as $socketId => $socketArr) {
            $isAvailable = !isset($this->socketIdRequestKeyMap[$socketId]);
            $existingSockAuthority = $socketArr['authority'];
            $existingSock = $socketArr['socket'];
            
            if (!($isAvailable && $existingSockAuthority == $socketAuthority)) {
                continue;
            }
            
            if (!$this->isSocketAlive($existingSock)) {
                @fclose($existingSock);
                unset($this->socketPool[$socketId]);
                continue;
            }
            
            return $existingSock;
        }
        
        return null;
    }
    
    /**
     * IMPORTANT: this is a test seam allowing us to mock socket IO for testing.
     * 
     * @param resource $socketResource
     * @return bool
     */
    protected function isSocketAlive($socketResource) {
        return is_resource($socketResource) && !feof($socketResource);
    }
    
    /**
     * Do our host concurrency limit settings allow a new connection to the relevant host?
     * 
     * @param string $socketAuthority
     * @return bool
     */
    private function isNewSocketConnectionAllowed($socketAuthority) {
        $currentConns = 0;
        foreach ($this->socketPool as $socketArr) {
            $currentConns += ($socketArr['authority'] == $socketAuthority);
        }
        
        $allowedConns = $this->getAttribute(self::ATTR_HOST_CONCURRENCY_LIMIT);
        
        return ($allowedConns > $currentConns);
    }
    
    /**
     * Open a new non-blocking socket connection, notifying listeners of the socket's status
     * 
     * @param Uri $socketUri
     * @throws ClientException On connection failure
     * @return resource
     */
    private function doNewSocketCheckout(Uri $socketUri) {
        $context = $this->buildSocketConnectionContext($socketUri);
        
        list($socket, $errNo, $errStr) = $this->makeSocket($socketUri, $context);
        
        if (false !== $socket) {
            stream_set_blocking($socket, 0);
            return $socket;
        } elseif ($sslError = $this->getOpenSslConnectError()) {
            throw new ClientException(
                'SSL socket connection failure: ' . $sslError
            );
        } else {
            $errorMsg = 'Socket connection failure: ' . $socketUri;
            $errorMsg .= !empty($errNo) ? "; [Error# $errNo] $errStr" : '';
            
            throw new ClientException($errorMsg);
        }
    }

    /**
     * IMPORTANT: this is a test seam allowing us to mock socket IO for testing.
     * 
     * @param Uri $socketUri
     * @param resource $context
     * @return array($resource, $errNo, $errStr)
     */
    protected function makeSocket(Uri $socketUri, $context) {
        $socket = @stream_socket_client(
            $socketUri,
            $errNo,
            $errStr,
            $this->getAttribute(self::ATTR_CONNECT_TIMEOUT),
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        // `stream_socket_client` modifies $errNo + $errStr by reference. Because we don't want to 
        // throw an exception here on connection failures we need to return these values along with
        // the $stream return value. In so doing we're able to easily mock connection results.
        return array($socket, $errNo, $errStr);
    }
    
    /**
     * @param Uri $socketUri
     * @return resource Returns socket connection context
     */
    private function buildSocketConnectionContext(Uri $socketUri) {
        if ('tls' != $socketUri->getScheme()) {
            return stream_context_create(array());
        }
        
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
       
        return stream_context_create(array('ssl' => $opts));
    }
    
    /**
     * @return string Returns OpenSSL error message or NULL if none exists
     */
    private function getOpenSslConnectError() {
        if ($tmpSslError = $this->nativeOpenSslErrorSeam()) {
            $sslError = $tmpSslError;
            while ($tmpSslError = $this->nativeOpenSslErrorSeam()) {
                $sslError = $tmpSslError;
            }
            return $sslError;
        } else {
            return null;
        }
    }
    
    /**
     * A test-seam for the native global ssl error retrieval function
     */
    protected function nativeOpenSslErrorSeam() {
        return openssl_error_string();
    }
    
    /**
     * Mark a socket connection as "available for use" by future or queued requests
     * 
     * @param string $requestKey
     * @return void
     */
    private function checkinSocket($requestKey) {
        $socket = $this->requestKeySocketMap[$requestKey];
        $socketId = (int) $socket;
        
        unset(
            $this->socketIdRequestKeyMap[$socketId],
            $this->requestKeySocketMap[$requestKey]
        );
    }
    
    /**
     * Close a socket connection
     * 
     * @param string $requestKey
     * @return void
     */
    private function closeSocket($requestKey) {
        $socket = $this->requestKeySocketMap[$requestKey];
        $socketId = (int) $socket;
        
        @fclose($socket);
        
        unset(
            $this->socketIdRequestKeyMap[$socketId],
            $this->requestKeySocketMap[$requestKey],
            $this->socketPool[$socketId]
        );
    }
    
    /**
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
    private function doStreamSelect(
        array $read,
        array $write,
        array $ex = null,
        $tvsec = 2,
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
    private function write($requestKey) {
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
        $socket = $this->requestKeySocketMap[$requestKey];

        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        
        $data = $request->getStartLineAndHeaders();
        $dataLen = strlen($data);
        $dataToWrite = substr($data, $state->headerBytesSent);
        
        $bytesWritten = $this->doSockWrite($socket, $dataToWrite);
        
        if (!$bytesWritten && !$this->isSocketAlive($socket)) {
            throw new ClientException(
                'Socket write failure encountered while sending request headers'
            );
        }
        
        $state->headerBytesSent += $bytesWritten;
        
        if ($state->headerBytesSent >= $dataLen) {
            if ($request->getBodyStream()) {
                $state->state = self::STATE_SEND_STREAM_REQUEST_BODY;
                $this->initializeStreamingRequestBodySend($requestKey);
            } elseif ($request->getBody()) {
                $state->state = self::STATE_SEND_BUFFERED_REQUEST_BODY;
            } else {
                $this->markRequestSendComplete($requestKey);
            }
        }
    }
    
    /**
     * @param string $requestKey
     * @return void
     */
    private function markRequestSendComplete($requestKey) {
        $state = $this->states[$requestKey];
        $state->state = self::STATE_READ_HEADERS;
    }
    
    /**
     * IMPORTANT: this is a test seam allowing us to mock socket IO for testing.
     * 
     * @param resource $socket
     * @param string $dataToWrite
     * @return int Returns number of bytes written or FALSE on failure
     */
    protected function doSockWrite($socket, $dataToWrite) {
        if ($bytesWritten = @fwrite($socket, $dataToWrite)) {
            $socketId = (int) $socket;
            $requestKey = $this->socketIdRequestKeyMap[$socketId];
            
            $this->updateStatsOnWrite($socketId, $requestKey, $bytesWritten);
        }
        
        return $bytesWritten;
    }
    
    /**
     * Update request stats when data is written to a socket
     * 
     * @param int $socketId
     * @param string $requestKey
     * @param int $writeDataLength
     * @return void
     */
    private function updateStatsOnWrite($socketId, $requestKey, $writeDataLength) {
        $now = microtime(true);
        
        $this->socketPool[$socketId]['bytesSent'] += $writeDataLength;
        $this->socketPool[$socketId]['activityAt'] = $now;
        
        $this->requestStatistics[$requestKey]['bytesSent'] += $writeDataLength;
        $this->requestStatistics[$requestKey]['lastSentAt'] = $now;
        
        $elapsedTime = ($now - $this->requestStatistics[$requestKey]['connectedAt']);
        $bytesPerSecond = ($this->requestStatistics[$requestKey]['bytesSent'] / $elapsedTime);
        
        // kilobytes per second (KB/s)
        $kBs = ($bytesPerSecond/1024);
        $this->requestStatistics[$requestKey]['avgUpKbPerSecond'] = round($kBs, 2);
    }
    
    /**
     * @param string $requestKey
     * @return void
     */
    private function initializeStreamingRequestBodySend($requestKey) {
        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        $state = $this->states[$requestKey];
        
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
        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        $state = $this->states[$requestKey];
        $socket = $this->requestKeySocketMap[$requestKey];
        
        $data = $request->getBody();
        $dataLen = strlen($data);
        $dataToWrite = substr($data, $state->bodyBytesSent);
        
        $bytesWritten = $this->doSockWrite($socket, $dataToWrite);
        if (!$bytesWritten && !$this->isSocketAlive($socket)) {
            throw new ClientException(
                'Socket write failure encountered while sending buffered request entity body'
            );
        }
        
        $state->bodyBytesSent += $bytesWritten;
        
        if ($state->bodyBytesSent >= $dataLen) {
            $this->markRequestSendComplete($requestKey);
        }
    }
    
    /**
     * Writes a streaming request body using a chunked Transfer-Encoding.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function writeStreamingRequestBody($requestKey) {
        $state = $this->states[$requestKey];
        $socket = $this->requestKeySocketMap[$requestKey];
        
        if ($state->streamRequestBodyChunkPos >= $state->streamRequestBodyChunkLength) {
            $chunk = $this->readChunkFromStreamRequestBody($requestKey);
            if (false === $chunk) {
                throw new ClientException(
                    "Failed reading data from stream request body"
                );
            }
            
            $rawChunkSize = strlen($chunk);
            
            $state->streamRequestBodyChunk = dechex($rawChunkSize) . "\r\n$chunk\r\n";
            $state->streamRequestBodyChunkPos = 0;
            $state->streamRequestBodyChunkLength = strlen($state->streamRequestBodyChunk);
            $state->streamRequestBodyChunkRawLength = $rawChunkSize;
        }
        
        $dataToWrite = substr($state->streamRequestBodyChunk, $state->streamRequestBodyChunkPos);
        
        $bytesWritten = $this->doSockWrite($socket, $dataToWrite);
        if (!$bytesWritten && !$this->isSocketAlive($socket)) {
            throw new ClientException(
                'Socket write failure encountered while sending buffered request entity body'
            );
        }
        
        $state->streamRequestBodyChunkPos += $bytesWritten;
        
        if ($state->streamRequestBodyChunkPos >= $state->streamRequestBodyChunkLength) {
            $state->streamRequestBodyPos += $state->streamRequestBodyChunkRawLength;
        }
        
        // If we just wrote the last chunk, change the state to "reading"
        if ($state->streamRequestBodyPos >= $state->streamRequestBodyLength
            && $state->streamRequestBodyChunk == "0\r\n\r\n"
        ) {
            $this->markRequestSendComplete($requestKey);
        }
    }
    
    /**
     * @param string $requestKey
     * @return string Returns string chunk from stream request body or FALSE on failure
     */
    protected function readChunkFromStreamRequestBody($requestKey) {
        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        $state = $this->states[$requestKey];
        
        $ioBufferSize = $this->getAttribute(self::ATTR_IO_BUFFER_SIZE);
        $outboundBodyStream = $request->getBodyStream();
        fseek($outboundBodyStream, $state->streamRequestBodyPos, SEEK_SET);
        
        return @fread($outboundBodyStream, $ioBufferSize);
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
    private function read($requestKey) {
        if ($this->isReadingHeaders($requestKey)) {
            $this->readHeaders($requestKey);
        }
        
        if ($this->isReadingBody($requestKey)) {
            $this->readBody($requestKey);
        }
        
        if ($this->isReadingToContentLength($requestKey)
            && $this->hasReceivedContentLength($requestKey)
        ) {
            $this->markComplete($requestKey);
        } elseif ($this->isReadingChunks($requestKey)
            && $this->hasReceivedFinalChunk($requestKey)
        ) {
            $this->dechunkResponseBody($requestKey);
            $this->markComplete($requestKey);
        }
        
        if ($this->isComplete($requestKey) && $this->isResponseBodyAllowed($requestKey)) {
            $this->validateResponseBody($requestKey);
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
        $socket = $this->requestKeySocketMap[$requestKey];
        
        $readData = $this->doSockRead($socket);
        
        if ($this->isReadDataEmpty($readData) && !$this->isSocketAlive($socket)) {
            return $this->handleSocketDisconnectDuringRead($requestKey);
        }
        
        // Ignore leading line-breaks in the raw message as per RFC2616 Section 4.1
        if (!$state->buffer) {
            $readData = ltrim($readData, "\r\n");
        }
        
        $state->buffer .= $readData;
        
        if (!$this->assignRawHeadersToResponse($requestKey)) {
            return;
        } elseif ($this->isResponseBodyAllowed($requestKey)) {
            $this->initializeResponseBodyRetrieval($requestKey);
        } else {
            $this->markComplete($requestKey);
        }
    }
    
    private function handleSocketDisconnectDuringRead($requestKey) {
        if ($this->isReadingHeaders($requestKey)) {
            throw new ClientException(
                'Socket failure encountered while retrieving response headers'
            );
        } elseif (!$this->isReadingChunks($requestKey)) {
            $this->markComplete($requestKey);
        } elseif ($this->hasReceivedFinalChunk($requestKey)) {
            $this->dechunkResponseBody($requestKey);
            $this->markComplete($requestKey);
        } else {
            throw new ClientException(
                'Socket connection lost before chunked response entity body fully received'
            );
        }
    }
    
    /**
     * When determining if empty data was returned from a read operation we must ensure that a
     * single byte string containing "0" was not returned otherwise we'll get false positives. An
     * `empty` check on its own can cause errors.
     * 
     * @param string $readData
     * @return bool
     */
    protected function isReadDataEmpty($readData) {
        return (empty($readData) && $readData !== '0');
    }
    
    /**
     * @param resource $socket
     * @return string $readData
     */
    protected function doSockRead($socket) {
        $ioBufferSize = $this->getAttribute(self::ATTR_IO_BUFFER_SIZE);
        $readData = @fread($socket, $ioBufferSize);
        
        if (!$this->isReadDataEmpty($readData)) {
            $socketId = (int) $socket;
            $requestKey = $this->socketIdRequestKeyMap[$socketId];
            $this->updateStatsOnRead($socketId, $requestKey, $readData);
        }
        
        return $readData;
    }
    
    /**
     * Update request stats when data is read from a socket
     * 
     * @param int $socketId
     * @param string $requestKey
     * @param int $readDataLength
     * @return void
     */
    private function updateStatsOnRead($socketId, $requestKey, $readData) {
        $now = microtime(true);
        
        $readDataLength = strlen($readData);
        
        $this->socketPool[$socketId]['bytesRecd'] += $readDataLength;
        $this->socketPool[$socketId]['activityAt'] = $now;
        
        $this->requestStatistics[$requestKey]['bytesRecd'] += $readDataLength;
        $this->requestStatistics[$requestKey]['lastRecdAt'] = $now;
        
        $elapsedTime = ($now - $this->requestStatistics[$requestKey]['lastSentAt']);
        $bytesPerSecond = ($this->requestStatistics[$requestKey]['bytesRecd'] / $elapsedTime);
        
        // kilobytes per second (KB/s)
        $kBs = ($bytesPerSecond/1024);
        $this->requestStatistics[$requestKey]['avgDownKbPerSecond'] = round($kBs, 2);
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

            /**
             * @var \Artax\Http\StdResponse $response
             */
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
        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        
        if (Request::HEAD == $request->getMethod()) {
            return false;
        }
        
        $status = $response->getStatusCode();
        if ($status == 204 || $status == 304 || ($status >= 100 && $status < 200)) {
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
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        $state = $this->states[$requestKey];
        
        $responseBody = $this->makeTempResponseBodyStream();
        
        if (!$responseBody) {
            throw new ClientException(
                'Failed initializing temporary response entity body storage stream'
            );
        }
        
        $bytesToWrite = strlen($state->buffer);
        
        if ($bytesToWrite) {
            $bytesWritten = $this->writeToTempResponseBodyStream($responseBody, $state->buffer);
            if ($bytesWritten !== $bytesToWrite) {
                throw new ClientException(
                    'Failed writing response entity body to temporary storage stream'
                );
            }
        }
        
        // Clear the buffer after writing its data to the response body stream.
        $response->setBody($responseBody);
        $state->buffer = null;
        
        if ($response->hasHeader('Content-Length')) {
            $state->state = self::STATE_READ_TO_CONTENT_LENGTH;
        } elseif ($this->isResponseChunked($response)) {
            $state->state = self::STATE_READ_CHUNKS;
        } else {
            $state->state = self::STATE_READ_TO_SOCKET_CLOSE;
        }
    }
    
    /**
     * IMPORTANT: This is a test seam allowing us to mock the fopen result when testing
     * 
     * Custom stream wrappers do not allow a return of FALSE on fopen calls, so we place this
     * operation in its own method to test behavior when the fopen fails.
     * 
     * @return resource Returns temporary response body storage stream or FALSE on failure
     */
    protected function makeTempResponseBodyStream() {
        return @fopen('php://temp', 'r+');
    }
    
    /**
     * @param resource $stream
     * @param string $dataToWrite
     * @return int Returns number of bytes written or FALSE on failure
     */
    protected function writeToTempResponseBodyStream($stream, $dataToWrite) {
        return @fwrite($stream, $dataToWrite);
    }
    
    /**
     * Do the response headers indicate a Transfer-Encoding value other than 'identity'?
     * 
     * "If a Transfer-Encoding header field (section 14.41) is present and has any value other 
     * than "identity", then the transfer-length is defined by use of the "chunked" 
     * transfer-coding (section 3.6), unless the message is terminated by closing the 
     * connection.
     * 
     * @param Response $response
     * @return bool
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
     */
    private function isResponseChunked(Response $response) {
        if (!$response->hasHeader('Transfer-Encoding')) {
            return false;
        } else {
            return strcmp('identity', $response->getHeader('Transfer-Encoding'));
        }
    }
    
    /**
     * Incrementally read the response body, updating the request state after each read.
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function readBody($requestKey) {
        $state = $this->states[$requestKey];
        $socket = $this->requestKeySocketMap[$requestKey];
        
        $responseBody = $this->getResponseBodyStream($requestKey);
        
        while (true) {
            $readData = $this->doSockRead($socket);
            if ($this->isReadDataEmpty($readData)) {
                break;
            } elseif (!$this->writeToTempResponseBodyStream($responseBody, $readData)) {
                throw new ClientException(
                    'Failed writing response entity body to temporary storage stream'
                );
            }
        }
        
        if (!$this->isSocketAlive($socket)) {
            $this->handleSocketDisconnectDuringRead($requestKey);
        }
    }
    
    private function getResponseBodyStream($requestKey) {
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        return $response->getBodyStream();
    }
    
    private function hasReceivedContentLength($requestKey) {
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        
        $responseBody = $response->getBodyStream();
        $totalBytesRecd = ftell($responseBody);
        $expectedLength = (int) $response->getHeader('Content-Length');
        
        return ($totalBytesRecd >= $expectedLength);
    }
    
    private function hasReceivedFinalChunk($requestKey) {
        $responseBody = $this->getResponseBodyStream($requestKey);
        fseek($responseBody, -96, SEEK_END);
        $endOfStream = stream_get_contents($responseBody);
        
        $pattern = ",(?:^0\r\n\r\n$)|(?:\r\n0+\r\n\r\n(?:\r\n)*$),";
        
        return preg_match($pattern, $endOfStream);
    }
    
    private function dechunkResponseBody($requestKey) {
        if (!$tmpBody = $this->makeTempResponseBodyStream()) {
            throw new ClientException(
                'Failed initializing temporary response entity body storage stream'
            );
        }
        
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        $responseBody = $response->getBodyStream();
        
        rewind($responseBody);
        stream_filter_prepend($responseBody, 'dechunk');
        stream_copy_to_stream($responseBody, $tmpBody);
        rewind($tmpBody);
        fclose($responseBody);
        
        $response->setBody($tmpBody);
    }
    
    /**
     * Validate the response entity body and assign it to the response object
     * 
     * @param string $requestKey
     * @throws ClientException
     * @return void
     */
    private function validateResponseBody($requestKey) {
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        
        $this->validateContentLength($response);
        $this->validateContentMd5($response);
        
        $responseBody = $response->getBodyStream();
        rewind($responseBody);
    }
    
    private function validateContentLength(Response $response) {
        if (!$response->hasHeader('Content-Length')) {
            return;
        }
        
        $responseBody = $response->getBodyStream();
        
        fseek($responseBody, 0, SEEK_END);
        $actualLength = ftell($responseBody);
        $expectedLength = $response->getHeader('Content-Length');
        
        if ($actualLength != $expectedLength) {
            throw new ClientException(
                'Content-Length mismatch: ' . $expectedLength . ' bytes expected, ' .
                $actualLength . ' bytes received'
            );
        }
    }
    
    private function validateContentMd5(Response $response) {
        if (!$response->hasHeader('Content-MD5')) {
            return;
        }
        
        $responseBody = $response->getBodyStream();
        
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
     * Check-in/close sockets as needed and perform redirect if necessary
     * 
     * @param string $requestKey
     * @return void
     */
    protected function completeResponse($requestKey) {
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
        }
    }
    
    /**
     * Should we keep this connection alive once the response is received?
     * 
     * @param string $requestKey
     * @return bool
     */
    private function shouldKeepConnectionAlive($requestKey) {
        /**
         * @var \Artax\Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        
        /**
         * @var \Artax\Http\Request $request
         */
        $request = $this->requests[$requestKey];
        
        if ($request->hasHeader('Connection')
            && !strcmp($request->getHeader('Connection'), 'close')
        ) {
            return false;
        }
        
        if ($response->hasHeader('Connection')
            && !strcmp($response->getHeader('Connection'), 'close')
        ) {
            return false;
        }
        
        if ($response->getHttpVersion() < 1.1
            && !$response->hasHeader('Connection')
        ) {
            return false;
        }
        
        if (!$this->getAttribute(self::ATTR_KEEP_CONNS_ALIVE)) {
            return false;
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
        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        
        /**
         * @var Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        
        /**
         * @var Uri $requestUri
         */
        $requestUri = $this->requestUris[$requestKey];
        
        
        if (!$response->hasHeader('Location')) {
            return false;
        }

        $redirectLocation = $requestUri->resolve($response->getHeader('Location'));
        $response->setHeader('Location', $redirectLocation->__toString());
        
        $followLocation = $this->getAttribute(self::ATTR_FOLLOW_LOCATION);
        if ($followLocation == self::FOLLOW_LOCATION_NONE) {
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
        if (!in_array($requestMethod, array(Request::GET, Request::HEAD))
            && !($canFollowUnsafe & $followLocation)
        ) {
            return false;
        }
        
        if ($this->willRedirectInfinitely($response, $redirectLocation)) {
            throw new ClientException(
                "Infinite redirect loop detected; cannot redirect to $redirectLocation"
            );
        }
        
        return true;
    }
    
    /**
     * @param Http\ChainableResponse $response
     * @param string $uri
     * @return bool
     */
    private function willRedirectInfinitely(ChainableResponse $response, $uri) {
        if ($uri == $response->getRequestUri()) {
            return true;
        }
        
        while ($response->hasPreviousResponse()) {
            $response = $response->getPreviousResponse();
            if ($uri == $response->getRequestUri()) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate a new set of request map values so we can follow the response's Location header
     * 
     * @param string $requestKey
     * @return void
     */
    protected function doRedirect($requestKey) {
        /**
         * @var Http\Request $request
         */
        $request = $this->requests[$requestKey];
        
        /**
         * @var Http\ChainableResponse $response
         */
        $response = $this->responses[$requestKey];
        
        $newUri = new Uri($response->getHeader('Location'));
        $this->requestUris[$requestKey] = $newUri;
        
        $newRequest = new StdRequest($newUri, $request->getMethod());
        $newRequest->setHttpVersion($request->getHttpVersion());
        $newRequest->setAllHeaders($request->getAllHeaders());
        $newRequest->setHeader('Host', $newUri->getAuthority());
        
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
    }
    
    /**
     * Close all open socket streams
     * 
     * @return int Returns the number of socket connections closed
     */
    public function closeAllSockets() {
        foreach ($this->socketPool as $sockArr) {
            @fclose($sockArr['socket']);
        }
        $connsClosed = count($this->socketPool);
        $this->socketPool = array();
        
        return $connsClosed;
    }
    
    /**
     * Close any open socket streams to the specified host
     * 
     * @param string $host
     * @return int Returns the number of socket connections closed
     */
    public function closeSocketsByHost($host) {
        $hostMatch = "$host:";
        
        $connsClosed = 0;
        foreach ($this->socketPool as $socketId => $sockArr) {
            if (0 === strpos($sockArr['authority'], $hostMatch)) {
                @fclose($sockArr['socket']);
                unset($this->socketPool[$socketId]);
                ++$connsClosed;
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
        $connsClosed = 0;
        $now = microtime(true);
        
        foreach ($this->socketPool as $socketId => $sockArr) {
            $secondsSinceActive = $now - $sockArr['activityAt'];
            
            if ($secondsSinceActive > $maxInactivitySeconds) {
                @fclose($sockArr['socket']);
                unset($this->socketPool[$socketId]);
                ++$connsClosed;
            }
        }
        
        return $connsClosed;
    }
    
    /**
     * Retrieve an array of statistics from the most recent request or request batch
     * 
     * Stat array keys:
     * 
     *  - connectedAt: The micro-timestamp when a connection was procured for the request
     *  - lastSentAt: The micro-timestamp of the final socket IO write
     *  - lastRecdAt: The micro-timestamp of the final socket IO read
     *  - bytesSent: The number of bytes sent to make the request
     *  - bytesRecd: The number of bytes read from the raw response message
     *  - avgUpKbPerSecond: The average upload speed in KB/s (kilobytes per second)
     *  - avgDownKbPerSecond: The average download speed in KB/s (kilobytes per second)
     * 
     * Note that for redirected responses, stats only reflect data from the final request/response 
     * in the redirect chain.
     * 
     * @param string $requestKey Required to retrieve stats for a specific request in a sendMulti
     *                           request batch. If Client::send was used, this value should be
     *                           zero (the default) or simply left unspecified.
     * @throws \Spl\DomainException On invalid request key
     * @return array
     */
    public function getRequestStats($requestKey = 0) {
        if (!isset($this->requestStatistics[$requestKey])) {
            throw new DomainException(
                'No request keys from the most recent retrieval match the specified value'
            );
        } else {
            return $this->requestStatistics[$requestKey];
        }
    }
}