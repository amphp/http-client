<?php

namespace Artax;

use Exception,
    StdClass,
    Traversable,
    SplObjectStorage,
    Spl\Mediator,
    Spl\TypeException,
    Spl\ValueException,
    Artax\Streams\StreamException,
    Artax\Streams\Stream,
    Artax\Streams\SocketStream,
    Artax\Streams\IoException,
    Artax\Streams\SocketDisconnectException,
    Artax\Streams\ConnectException,
    Artax\Http\Request,
    Artax\Http\StdRequest,
    Artax\Http\Response,
    Artax\Http\ChainableResponse;

class Client {
    
    const USER_AGENT = 'Artax/0.1 (PHP5.3+)';
    
    const ATTR_CONNECT_TIMEOUT =  105;
    const ATTR_FOLLOW_LOCATION = 110;
    const ATTR_HOST_CONCURRENCY_LIMIT = 115;
    const ATTR_IO_BUFFER_SIZE = 120;
    const ATTR_KEEP_ALIVES = 125;
    const ATTR_AUTO_REFERER_ON_FOLLOW = 130;
    
	const ATTR_SSL_VERIFY_PEER = 900;
	const ATTR_SSL_ALLOW_SELF_SIGNED = 905;
	const ATTR_SSL_CA_FILE = 910;
	const ATTR_SSL_CA_PATH = 915;
	const ATTR_SSL_LOCAL_CERT = 920;
	const ATTR_SSL_LOCAL_CERT_PASSPHRASE = 925;
	const ATTR_SSL_CN_MATCH = 930;
	const ATTR_SSL_VERIFY_DEPTH = 935;
	const ATTR_SSL_CIPHERS = 940;
    
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
    
    /**
     * @var bool
     */
    private $useKeepAlives = true;
    
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
    private $ioBufferSize = 8192;
    
    /**
     * @var int
     */
    private $followLocation = self::FOLLOW_LOCATION_ON_3XX;
    
    /**
     * @var bool
     */
    private $autoRefererOnFollow = true;
    
    /**
     * @var bool
     */
    private $sslVerifyPeer = true;
    
    /**
     * @var bool
     */
    private $sslAllowSelfSigned = false;
    
    /**
     * @var string
     */
    private $sslCertAuthorityFile;
    
    /**
     * @var string
     */
    private $sslCertAuthorityDirPath;
    
    /**
     * @var string
     */
    private $sslLocalCertFile = '';
    
    /**
     * @var string
     */
    private $sslLocalCertPassphrase;
    
    /**
     * @var string
     */
    private $sslCommonNameMatch;
    
    /**
     * @var int
     */
    private $sslVerifyDepth = 5;
    
    /**
     * @var string
     */
    private $sslCiphers = 'DEFAULT';
    
    /**
     * @var array
     */
    private $sockPool = array();
    
    /**
     * @var \Spl\Mediator
     */
    private $mediator;
    
    /**
     * Maps request instances to the corresponding keys from the original request list
     * @var SplObjectStorage
     */
    private $requestKeyMap;
    
    /**
     * A list of requests waiting for socket connection assignment
     * @var SplObjectStorage
     */
    private $queuedRequests;
    
    /**
     * Maps request instances to their assigned socket connection
     * @var SplObjectStorage
     */
    private $requestSocketMap;
    
    /**
     * Maps request instances to state/progress metrics during the retrieval process
     * @var SplObjectStorage
     */
    private $requestProgressMap;
    
    /**
     * Maps request instances to their corresponding response object
     * @var SplObjectStorage
     */
    private $requestResponseMap;
    
    /**
     * Maps response objects to the memory stream used to store the entity body during retrieval
     * @var SplObjectStorage
     */
    private $responseBodyStreamMap;
    
    /**
     * Maps socket stream resource IDs to in-progress request instances
     * @var array
     */
    private $resourceRequestMap;
    
    /**
     * Maintains the order of requests from the original list
     * @var array
     */
    private $requestKeyOrder;
    
    /**
     * @param \Spl\Mediator $mediator
     * @return void
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * Assign multiple Client attributes at once
     * 
     * @param mixed $arrayOrTraversable
     * @return void
     */
    public function setAllAttributes($arrayOrTraversable) {
        foreach ($arrayOrTraversable as $attr => $val) {
            $this->setAttribute($attr, $val);
        }
    }
    
    /**
     * Assign optional Client attributes
     * 
     * @param int $attr
     * @param mixed $val
     * @return void
     * @throws ValueException On invalid attribute
     */
    public function setAttribute($attr, $val) {
        switch ($attr) {
            case self::ATTR_KEEP_ALIVES:
                $this->setKeepAlives($val);
                break;
            case self::ATTR_CONNECT_TIMEOUT:
                $this->setConnectTimeout($val);
                break;
            case self::ATTR_HOST_CONCURRENCY_LIMIT:
                $this->setHostConcurrencyLimit($val);
                break;
            case self::ATTR_IO_BUFFER_SIZE:
                $this->setIoBufferSize($val);
                break;
            case self::ATTR_FOLLOW_LOCATION:
                $this->setFollowLocation($val);
                break;
            case self::ATTR_AUTO_REFERER_ON_FOLLOW:
                $this->setAutoRefererOnFollow($val);
                break;
            case self::ATTR_SSL_VERIFY_PEER:
                $this->setSslVerifyPeer($val);
                break;
            case self::ATTR_SSL_ALLOW_SELF_SIGNED:
                $this->setSslAllowSelfSigned($val);
                break;
            case self::ATTR_SSL_CA_FILE:
                $this->setSslCertAuthorityFile($val);
                break;
            case self::ATTR_SSL_CA_PATH:
                $this->setSslCertAuthorityDirPath($val);
                break;
            case self::ATTR_SSL_LOCAL_CERT:
                $this->setSslLocalCertFile($val);
                break;
            case self::ATTR_SSL_LOCAL_CERT_PASSPHRASE:
                $this->setSslLocalCertPassphrase($val);
                break;
            case self::ATTR_SSL_CN_MATCH:
                $this->setSslCommonNameMatch($val);
                break;
            case self::ATTR_SSL_VERIFY_DEPTH:
                $this->setSslVerifyDepth($val);
                break;
            case self::ATTR_SSL_CIPHERS:
                $this->setSslCiphers($val);
                break;
            default:
                throw new ValueException(
                    'Invalid attribute: Client::' . $attr . ' does not exist'
                );
        }
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    private function setKeepAlives($boolFlag) {
        $this->useKeepAlives = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param int $secondsUntilTimeout
     * @return void
     */
    private function setConnectTimeout($secondsUntilTimeout) {
        $this->connectTimeout = (int) $secondsUntilTimeout;
    }
    
    /**
     * @param int $maxConnections
     * @return void
     */
    private function setHostConcurrencyLimit($maxConnections) {
        $maxConnections = (int) $maxConnections;
        $maxConnections = $maxConnections < 1 ? 1 : $maxConnections;
        $this->hostConcurrencyLimit = $maxConnections;
    }
    
    /**
     * @param int $bytes
     * @return void
     */
    private function setIoBufferSize($bytes) {
        $bytes = (int) $bytes;
        $this->ioBufferSize = $bytes <= 0 ? self::ATTR_IO_BUFFER_SIZE : $bytes;
    }
    
    /**
     * @param int $bitmask
     * @return void
     */
    private function setFollowLocation($bitmask) {
        $this->followLocation = (int) $bitmask;
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    private function setAutoRefererOnFollow($boolFlag) {
        $this->autoRefererOnFollow = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    private function setSslVerifyPeer($boolFlag) {
        $this->sslVerifyPeer = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    private function setSslAllowSelfSigned($boolFlag) {
        $this->sslAllowSelfSigned = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param string $certAuthorityFilePath
     * @return void
     */
    private function setSslCertAuthorityFile($certAuthorityFilePath) {
        $this->sslCertAuthorityFile = $certAuthorityFilePath;
    }
    
    /**
     * @param string $certAuthorityDirPath
     * @return void
     */
    private function setSslCertAuthorityDirPath($certAuthorityDirPath) {
        $this->sslCertAuthorityDirPath = $certAuthorityDirPath;
    }
    
    /**
     * @param string $localCertFilePath
     * @return void
     */
    private function setSslLocalCertFile($localCertFilePath) {
        $this->sslLocalCertFile = $localCertFilePath;
    }
    
    /**
     * @param string $localCertPassphrase
     * @return void
     */
    private function setSslLocalCertPassphrase($localCertPassphrase) {
        $this->sslLocalCertPassphrase = $localCertPassphrase;
    }
    
    /**
     * @param string $commonNameMatch
     * @return void
     */
    private function setSslCommonNameMatch($commonNameMatch) {
        $this->sslCommonNameMatch = $commonNameMatch;
    }
    
    /**
     * @param int $depth
     * @return void
     */
    private function setSslVerifyDepth($depth) {
        $this->sslVerifyDepth = (int) $depth;
    }
    
    /**
     * @param string $cipherList
     * @return void
     */
    private function setSslCiphers($cipherList) {
        $this->sslCiphers = $cipherList;
    }
    
    /**
     * Make an HTTP request
     * 
     * @param Http\Request $request
     * @return Http\ChainableResponse
     * @throws ClientException
     */
    public function send(Request $request) {
        $this->buildRequestMaps(array($request));
        $this->execute();
        
        $this->requestResponseMap->rewind();
        $response = $this->requestResponseMap->getInfo();
        
        if ($response instanceof ChainableResponse) {
            return $response;
        } elseif ($response instanceof ClientException) {
            throw $response;
        } elseif ($response instanceof Exception) {
            throw new ClientException(
                $response->getMessage(),
                null,
                $response
            );
        }
    }
    
    /**
     * @param mixed $requests
     * @return array
     */
    private function buildRequestMaps($requests) {
        $this->requestKeyMap = new SplObjectStorage();
        $this->queuedRequests = new SplObjectStorage();
        $this->requestSocketMap = new SplObjectStorage();
        $this->requestResponseMap = new SplObjectStorage();
        $this->requestProgressMap = new SplObjectStorage();
        $this->responseBodyStreamMap = new SplObjectStorage();
        
        $this->resourceRequestMap = array();
        $this->requestKeyOrder = array();
        
        foreach ($requests as $key => $request) {
            $this->normalizeRequestHeaders($request);
            $this->mediator->notify(self::EVENT_REQUEST, $key, $request);
            
            $this->requestKeyOrder[$key] = null;
            $this->requestKeyMap->attach($request, $key);
            $this->queuedRequests->attach($request);
            $this->requestProgressMap->attach($request, new ClientRequestState());
            $this->requestResponseMap->attach($request, new ChainableResponse());
            $this->assignStreamToRequest($request);
        }
    }
    
    /**
     * @param Http\Request $request
     * @return void
     */
    private function normalizeRequestHeaders(Request $request) {
        $request->setHeader('User-Agent', self::USER_AGENT);
        $request->setHeader('Host', $request->getAuthority());
        
        if ($request->getBodyStream()) {
            $request->setHeader('Transfer-Encoding', 'chunked');
        } elseif ($entityBody = $request->getBody()) {
            $request->setHeader('Content-Length', strlen($entityBody));
        } else {
            $request->removeHeader('Content-Length');
            $request->removeHeader('Transfer-Encoding');
        }
        
        $request->removeHeader('Accept-Encoding');
        
        if (!$this->useKeepAlives) {
            $request->setHeader('Connection', 'close');
        }
    }
    
    /**
     * @param Http\Request $request
     * @return bool
     */
    private function assignStreamToRequest(Request $request) {
        try {
            $stream = $this->checkoutStream($request);
            if ($stream) {
                $this->queuedRequests->detach($request);
                $this->requestSocketMap->attach($request, $stream);
                $this->resourceRequestMap[(int) $stream->getResource()] = $request;
                $progress = $this->requestProgressMap->offsetGet($request);
                $progress->state = ClientRequestState::SENDING_REQUEST_HEADERS;
            }
        } catch (ConnectException $e) {
            $this->queuedRequests->detach($request);
            $this->requestResponseMap->attach($request, $e);
            $progress = $this->requestProgressMap->offsetGet($request);
            $progress->state = ClientRequestState::ERROR;
        }
    }
    
    /**
     * @param Http\Request $request
     * @throws Streams\ConnectException
     * @return Streams\SocketStream Returns NULL if max concurrency limit already reached
     */
    private function checkoutStream(Request $request) {
        $socketUri = $this->buildSocketUri($request);
        $socketUriString = $socketUri->__toString();
        
        if (!isset($this->sockPool[$socketUriString])) {
            $this->sockPool[$socketUriString] = new SplObjectStorage();
        }
        
        foreach ($this->sockPool[$socketUriString] as $stream) {
            $isInUse = $this->sockPool[$socketUriString]->getInfo();
            if (!$isInUse) {
                $this->sockPool[$socketUriString]->setInfo(true);
                $requestKey = $this->requestKeyMap->offsetGet($request);
                $this->mediator->notify(self::EVENT_SOCK_CHECKOUT, $requestKey, $stream);
                return $stream;
            }
        }
        
        $openHostStreams = count($this->sockPool[$socketUriString]);
        
        if ($this->hostConcurrencyLimit > $openHostStreams) {
            $stream = $this->makeStream($socketUri);
            
            if (0 === strcmp('tls', $socketUri->getScheme())) {
                $contextOpts = $this->buildSslContext($socketUri);
            } else {
                $contextOpts = array();
            }
            
            $stream->setConnectTimeout($this->connectTimeout);
            $stream->setContextOptions($contextOpts);
            $stream->open();
            
            stream_set_blocking($stream->getResource(), 0);
            
            $requestKey = $this->requestKeyMap->offsetGet($request);
            $this->mediator->notify(self::EVENT_SOCK_OPEN, $requestKey, $stream);
            
            $this->sockPool[$socketUriString]->attach($stream, true);
            $this->mediator->notify(self::EVENT_SOCK_CHECKOUT, $requestKey, $stream);
            
            return $stream;
        } else {
            return null;
        }
    }

    /**
     * @param Http\Request $request
     * @throws ValueException
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
                throw new ValueException(
                    "Invalid request URI scheme: $requestScheme. http:// or https:// required."
                );
        }
        
        $uriStr = $scheme . '://' . $request->getHost() . ':' . $request->getPort();
        
        return new Uri($uriStr);
    }
    
    /**
     * @param Uri $socketUri
     * @return Streams\SocketStream
     */
    protected function makeStream(Uri $socketUri) {
        return new SocketStream($socketUri);
    }

    /**
     * @param Uri $socketUri
     * @return array
     */
    private function buildSslContext(Uri $socketUri) {
        $opts = array(
            'verify_peer' => $this->sslVerifyPeer,
            'allow_self_signed' => $this->sslAllowSelfSigned,
            'verify_depth' => $this->sslVerifyDepth,
            'cafile' => $this->sslCertAuthorityFile,
            'CN_match' => $this->sslCommonNameMatch ?: $socketUri->getHost(),
            'ciphers' => $this->sslCiphers
        );
        
        if ($this->sslCertAuthorityDirPath) {
            $opts['capath'] = $this->sslCertAuthorityDirPath;
        }
        if ($this->sslLocalCertFile) {
            $opts['local_cert'] = $this->sslLocalCertFile;
        }
        if ($this->sslLocalCertPassphrase) {
            $opts['passphrase'] = $this->sslLocalCertPassphrase;
        }
       
        return array('ssl' => $opts);
    }
    
    /**
     * @param Streams\SocketStream $stream
     * @return void
     */
    private function checkinStream(SocketStream $stream) {
        $requestKey = $this->getRequestKeyFromSocketStream($stream);
        $socketUriString = $stream->getUri();
        $this->sockPool[$socketUriString]->attach($stream, false);
        $this->mediator->notify(self::EVENT_SOCK_CHECKIN, $requestKey, $stream);
    }
    
    /**
     * @param Streams\SocketStream $stream
     * @return string
     */
    private function getRequestKeyFromSocketStream(SocketStream $stream) {
        $streamId = (int) $stream->getResource();
        $request = $this->resourceRequestMap[$streamId];
        
        return $this->requestKeyMap->offsetGet($request);
    }
    
    /**
     * @param Streams\SocketStream $stream
     * @return void
     */
    private function closeStream(SocketStream $stream) {
        $requestKey = $this->getRequestKeyFromSocketStream($stream);
        $socketUriString = $stream->getUri();
        $this->sockPool[$socketUriString]->detach($stream);
        
        $stream->close();
        $this->mediator->notify(self::EVENT_SOCK_CLOSE, $requestKey, $stream);
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
        $connectionsClosed = 0;
        $now = time();
        
        foreach ($this->sockPool as $objStorage) {
            foreach ($objStorage as $stream) {
                if ($now - $stream->getActivityTimestamp() > $maxInactivitySeconds) {
                    $stream->close();
                    ++$connectionsClosed;
                }
            }
        }
        
        return $connectionsClosed;
    }
    
    /**
     * @return void
     */
    private function execute() {
        while (!$this->hasCompletedAllRequests()) {
            
            list($read, $write) = $this->getSelectableStreams();
            if (empty($read) &&  empty($write)) {
                continue;
            }
            
            list($read, $write) = $this->doStreamSelect($read, $write);
            
            foreach ($write as $resource) {
                $request = $this->resourceRequestMap[(int) $resource];
                try {
                    $this->doRequestWrite($request);
                } catch (Exception $e) {
                    $this->requestResponseMap->attach($request, $e);
                    $progress = $this->requestProgressMap->offsetGet($request);
                    $progress->state = ClientRequestState::ERROR;
                }
            }
            
            foreach ($read as $resource) {
                $request = $this->resourceRequestMap[(int) $resource];
                try {
                    $this->doResponseRead($request);
                } catch (Exception $e) {
                    $this->requestResponseMap->attach($request, $e);
                    $progress = $this->requestProgressMap->offsetGet($request);
                    $progress->state = ClientRequestState::ERROR;
                }
            }
        }
    }
    
    /**
     * @return bool
     */
    private function hasCompletedAllRequests() {
        $completedCount = 0;
        $this->requestProgressMap->rewind();
        foreach ($this->requestProgressMap as $request) {
            $progress = $this->requestProgressMap->getInfo();
            $isComplete = ($progress->state >= ClientRequestState::RESPONSE_RECEIVED);
            $completedCount += $isComplete;
        }
        
        return $completedCount == count($this->requestProgressMap);
    }
    
    /**
     * @return array
     */
    private function getSelectableStreams() {
        $read = $write = array();
        
        foreach ($this->queuedRequests as $request) {
            $this->assignStreamToRequest($request);
        }
        
        foreach ($this->requestSocketMap as $request) {
            $progress = $this->requestProgressMap->offsetGet($request);
            $state = $progress->state;
            
            if ($state >= ClientRequestState::RESPONSE_RECEIVED) {
                continue;   
            }
            
            $stream = $this->requestSocketMap->getInfo();
            
            if ($state < ClientRequestState::READING_HEADERS) {
                $write[] = $stream->getResource();
            } elseif ($state < ClientRequestState::RESPONSE_RECEIVED) {
                $read[] = $stream->getResource();
            }
        }
        
        return array($read, $write);
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
     * @param Http\Request $request
     * @return void
     */
    private function doRequestWrite($request) {
        $progress = $this->requestProgressMap->offsetGet($request);
        $stream = $this->requestSocketMap->offsetGet($request);
        
        switch ($progress->state) {
            case ClientRequestState::SENDING_REQUEST_HEADERS:
                $this->writeRequestHeaders($request, $stream, $progress);
                break;
            case ClientRequestState::SENDING_BUFFERED_REQUEST_BODY:
                $this->writeBufferedRequestBody($request, $stream, $progress);
                break;
            case ClientRequestState::SENDING_STREAM_REQUEST_BODY:
                $this->writeStreamingRequestBody($request, $stream, $progress);
                break;
        }
    }
    
    /**
     * @param Http\Request $request
     * @param Streams\SocketStream
     * @param ClientRequestState $progress
     * @throws Streams\IoException
     * @return void
     */
    private function writeRequestHeaders($request, $stream, $progress) {
        $data = $this->buildRawRequestHeaders($request);
        $dataLen = strlen($data);
        $dataToWrite = substr($data, $progress->headerBytesSent);
        
        $bytesWritten = $stream->write($dataToWrite);
        $actualDataWritten = substr($data, 0, $bytesWritten);
        $requestKey = $this->requestKeyMap->offsetGet($request);
        
        $this->mediator->notify(
            self::EVENT_SOCK_IO_WRITE,
            $requestKey,
            $actualDataWritten,
            $bytesWritten
        );
        
        $progress->headerBytesSent += $bytesWritten;
        
        if ($progress->headerBytesSent >= $dataLen) {
            if ($request->getBodyStream()) {
                $progress->state = ClientRequestState::SENDING_STREAM_REQUEST_BODY;
            } elseif ($request->getBody()) {
                $progress->state = ClientRequestState::SENDING_BUFFERED_REQUEST_BODY;
            } else {
                $progress->state = ClientRequestState::READING_HEADERS;
            }
        }
    }
    
    /**
     * @param Http\Request $request
     * @return string
     */
    private function buildRawRequestHeaders(Request $request) {
        if ('CONNECT' != $request->getMethod()) {
            $data = $request->getRequestLine() . "\r\n" . $request->getRawHeaders() . "\r\n";
        } else {
            $data = 'CONNECT ' . $request->getAuthority() . 'HTTP/' . $request->getHttpVersion();
            $data.= "\r\n" . $request->getRawHeaders() . "\r\n";
        }
        
        return $data;
    }
    
    /**
     * @param Http\Request $request
     * @param Streams\SocketStream
     * @param ClientRequestState $progress
     * @throws Streams\IoException
     * @return void
     */
    private function writeBufferedRequestBody($request, $stream, $progress) {
        $data = $request->getBody();
        $dataLen = strlen($data);
        $dataToWrite = substr($data, $progress->bodyBytesSent);
        
        $bytesWritten = $stream->write($dataToWrite);
        $actualDataWritten = substr($data, 0, $bytesWritten);
        $requestKey = $this->requestKeyMap->offsetGet($request);
        
        $this->mediator->notify(
            self::EVENT_SOCK_IO_WRITE,
            $requestKey,
            $actualDataWritten,
            $bytesWritten
        );
        
        $progress->bodyBytesSent += $bytesWritten;
        
        if ($progress->bodyBytesSent >= $dataLen) {
            $progress->state = ClientRequestState::READING_HEADERS;
        }
    }

    /**
     * @param Http\Request $request
     * @param Streams\SocketStream
     * @param ClientRequestState $progress
     * @throws Streams\StreamException
     * @throws Streams\IoException
     * @return void
     */
    private function writeStreamingRequestBody($request, $stream, $progress) {
        $dataStream = $request->getBodyStream();
        fseek($data, 0, SEEK_END);
        $dataStreamLen = ftell($dataStream);
        rewind($dataStream);
        
        $bodyBuffer = null;
        while (true) {
            if (is_null($bodyBuffer)) {
                $data = @fread($dataStream, $this->ioBufferSize);
                if (false === $data) {
                    throw new StreamException(
                        "Failed reading request body from $data"
                    );
                }
                
                $chunkLength = strlen($data);
                $bodyBuffer = dechex($chunkLength) . "\r\n$readData\r\n";
                $bodyBufferBytes = strlen($bodyBuffer);
                $bodyBufferBytesSent = 0;
            }
            
            $dataToWrite = substr($bodyBuffer, $bodyBufferBytesSent);
            
            $bytesWritten = $stream->write($dataToWrite);
            if (empty($bytesWritten)) {
                continue;
            }
            
            $actualDataWritten = substr($dataToWrite, 0, $bytesWritten);
            $requestKey = $this->requestKeyMap->offsetGet($request);
            
            $this->mediator->notify(
                self::EVENT_SOCK_IO_WRITE,
                $requestKey,
                $actualDataWritten,
                $bytesWritten
            );
            
            $bodyBufferBytesSent += $bytesWritten;
            if ($bodyBufferBytesSent < $bodyBufferBytes) {
                continue;
            }
            
            $progress->bodyBytesSent += $chunkLength;
            
            if ($progress->bodyBytesSent == ftell($dataStream) && $bodyBuffer == "0\r\n\r\n") {
                $progress->state = ClientRequestState::READING_HEADERS;
                break;
            } else {
                $bodyBuffer = null;
            }
        }
    }
    
    /**
     * @param Http\Request $request
     * @return void
     */
    private function doResponseRead(Request $request) {
        $progress = $this->requestProgressMap->offsetGet($request);
        $stream = $this->requestSocketMap->offsetGet($request);
        $response = $this->requestResponseMap->offsetGet($request);
        
        if ($progress->state == ClientRequestState::READING_HEADERS) {
            $this->readHeaders($request, $response, $stream, $progress);
        }
        
        if ($progress->state > ClientRequestState::READING_HEADERS
            && $progress->state < ClientRequestState::RESPONSE_RECEIVED
        ) {
            $this->readBody($request, $response, $stream, $progress);
        }
        
        if ($progress->state == ClientRequestState::RESPONSE_RECEIVED) {
            $this->validateResponseContentLength($response);
            
            if ($this->shouldKeepAlive($response)) {
                $this->checkinStream($stream);
            } else {
                $this->closeStream($stream);
            }
            
            if ($this->canRedirect($request, $response)) {
                $this->doRedirect($request, $response, $progress);
            } else {
                $requestKey = $this->requestKeyMap->offsetGet($request);
                $this->mediator->notify(
                    self::EVENT_RESPONSE,
                    $requestKey,
                    $response
                );
            }
        }
    }
    
    /**
     * @param Http\Response $response
     * @throws ClientException
     * @return void
     */
    private function validateResponseContentLength(Response $response) {
        if (!$response->hasHeader('Content-Length')) {
            return;
        }
        
        $entityBodyStream = $response->getBodyStream();
        fseek($entityBodyStream, 0, SEEK_END);
        $actualLength = ftell($entityBodyStream);
        $expectedLength = $response->getHeader('Content-Length');
        rewind($entityBodyStream);
        
        if ($actualLength != $expectedLength) {
            throw new ClientException(
                'Content-Length mismatch: ' . $expectedLength . ' bytes expected, ' .
                $actualLength . ' bytes received'
            );
        }
    }
    
    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @param Streams\SocketStream
     * @param ClientRequestState $progress
     * @throws Streams\IoException
     * @return void
     */
    private function readHeaders($request, $response, $stream, $progress) {
        if (!$readData = $stream->read($this->ioBufferSize)) {
            return;
        }
        $progress->buffer .= $readData;
        
        $requestKey = $this->requestKeyMap->offsetGet($request);
        
        $this->mediator->notify(
            self::EVENT_SOCK_IO_READ,
            $requestKey,
            $readData,
            strlen($readData)
        );
        
        $bodyStartPos = strpos(ltrim($progress->buffer), "\r\n\r\n");
        if (false === $bodyStartPos) {
            return;
        }
        
        $startLineAndHeaders = substr($progress->buffer, 0, $bodyStartPos);
        list($startLine, $headers) = explode("\r\n", $startLineAndHeaders, 2);
        $progress->buffer = substr($progress->buffer, $bodyStartPos + 4);
        
        $response->setStartLine($startLine);
        $response->setAllRawHeaders($headers);
        
        if (!$this->allowsEntityBody($request, $response)) {
            $progress->state = ClientRequestState::RESPONSE_RECEIVED;
        } elseif ($this->responseIsChunked($response)) {
            $progress->state = ClientRequestState::READING_CHUNKS;
        } elseif ($response->hasHeader('Content-Length')) {
            $progress->state = ClientRequestState::READING_UNTIL_LENGTH_REACHED;
        } else {
            $progress->state = ClientRequestState::READING_UNTIL_CLOSE;
        }
    }
    
    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @return bool
     */
    private function allowsEntityBody(Request $request, Response $response) {
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
     * @param Http\Response $response
     * @return bool
     */
    private function responseIsChunked(Response $response) {
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
        return strcmp($transferEncoding, 'identity');
    }
    
    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @param Streams\SocketStream
     * @param ClientRequestState $progress
     * @throws Streams\IoException
     * @return bool
     */
    private function readBody($request, $response, $stream, $progress) {
        if (!$this->responseBodyStreamMap->contains($response)) {
            $responseBodyStream = $this->openMemoryStream();
            $responseBodyStream->write($progress->buffer);
            $progress->buffer = '';
            $this->responseBodyStreamMap->attach($response, $responseBodyStream);
        } else {
            $responseBodyStream = $this->responseBodyStreamMap->offsetGet($response);
        }
        
        $readData = $stream->read($this->ioBufferSize);
        if (!$readData && !$stream->isConnected()) {
            $progress->state = ClientRequestState::RESPONSE_RECEIVED;
        } else {
            $responseBodyStream->write($readData);
            $requestKey = $this->requestKeyMap->offsetGet($request);
            $this->mediator->notify(
                self::EVENT_SOCK_IO_READ,
                $requestKey,
                $readData,
                strlen($readData)
            );
        }
        
        if ($progress->state == ClientRequestState::READING_UNTIL_LENGTH_REACHED) {
            $bytesRecd = ftell($responseBodyStream->getResource());
            if ($bytesRecd >= $response->getHeader('Content-Length')) {
                $progress->state = ClientRequestState::RESPONSE_RECEIVED;
            }
        } elseif ($progress->state == ClientRequestState::READING_CHUNKS) {
            fseek($responseBodyStream->getResource(), -1024, SEEK_END);
            $endOfStream = stream_get_contents($responseBodyStream->getResource());
            if (preg_match(",\r\n0+\r\n\r\n$,", $endOfStream)) {
                stream_filter_prepend($responseBodyStream->getResource(), 'dechunk');
                $progress->state = ClientRequestState::RESPONSE_RECEIVED;
            }
        }
        
        if ($progress->state == ClientRequestState::RESPONSE_RECEIVED) {
            rewind($responseBodyStream->getResource());
            $response->setBody($responseBodyStream->getResource());
            $this->responseBodyStreamMap->detach($response);
        }
    }
    
    /**
     * @return resource
     * @throws Streams\StreamException
     */
    protected function openMemoryStream() {
        $stream = new Stream('php://temp', 'r+');
        $stream->open();
        return $stream;
    }
    
    /**
     * @param Http\Response $response
     * @return bool
     */
    private function shouldKeepAlive(Response $response) {
        if (!$this->useKeepAlives) {
            return false;
        }
        if (!$response->hasHeader('Connection') && $response->getHttpVersion() == '1.1') {
            return true;
        }
        if ($response->hasHeader('Connection')
            && strcmp($response->getHeader('Connection'), 'close')
        ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @param array $redirectHistory
     * @return bool
     */
    private function canRedirect(Request $request, Response $response) {
        if ($this->followLocation == self::FOLLOW_LOCATION_NONE
            || !$response->hasHeader('Location')
        ) {
            return false;
        }
        
        $this->normalizeRedirectLocation($request, $response);
        
        $statusCode = $response->getStatusCode();
        
        $canFollow3xx = self::FOLLOW_LOCATION_ON_3XX;
        if ($statusCode >= 300 && $statusCode < 400 && !($canFollow3xx & $this->followLocation)) {
           return false;
        }
        
        $canFollow2xx = self::FOLLOW_LOCATION_ON_2XX;
        if ($statusCode >= 200 && $statusCode < 300 && !($canFollow2xx & $this->followLocation)) {
           return false;
        }
        
        $requestMethod = $request->getMethod();
        $canFollowUnsafe = self::FOLLOW_LOCATION_ON_UNSAFE_METHOD;
        if (!in_array($requestMethod, array('GET', 'HEAD'))
            && !($canFollowUnsafe & $this->followLocation)
        ) {
            return false;
        }
        
        return true;
    }

    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @return void
     */
    private function normalizeRedirectLocation(Request $request, Response $response) {
        $locationHeader = $response->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $request->getScheme() . '://' . $request->getAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $response->setHeader('Location', $newLocation);
            $response->appendHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
        }
    }
    
    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @param ClientRequestState $progress
     * @return void
     */
    private function doRedirect(Request $request, Response $response, ClientRequestState $progress) {
        $oldLocation = $request->getUri();
        $newLocation = $response->getHeader('Location');
        
        $progress->redirectHistory[] = $oldLocation;
        
        if (in_array($newLocation, $progress->redirectHistory)) {
            $response->appendHeader(
                'Warning',
                "199 Infinite redirect loop detected: cannot redirect to $newLocation"
            );
            return;
        }
        
        $newRequest = new StdRequest($newLocation, $request->getMethod());
        $newRequest->setHttpVersion($request->getHttpVersion());
        $newRequest->setAllHeaders($request->getAllHeaders());
        $newRequest->setHeader('Host', $newRequest->getAuthority());
        
        if ($this->autoRefererOnFollow) {
            $newRequest->setHeader('Referer', $oldLocation);
        }
        
        if ($newRequest->allowsEntityBody()) {
            $streamBody = $request->getBodyStream();
            if ($streamBody) {
                $newRequest->setBody($streamBody);
            } else {
                $newRequest->setBody($request->getBody());
            }
        }
        
        $progress->resetForRedirect();
        $newResponse = new ChainableResponse();
        $newResponse->setPreviousResponse($response);
        
        $requestKey = $this->requestKeyMap->offsetGet($request);
        $this->requestKeyMap->detach($request);
        $this->requestKeyMap->attach($newRequest, $requestKey);
        
        $this->queuedRequests->attach($newRequest);
        
        $this->requestProgressMap->detach($request);
        $this->requestProgressMap->attach($newRequest, $progress);
        
        $this->requestSocketMap->detach($request);
        
        $this->requestResponseMap->detach($request);
        $this->requestResponseMap->attach($newRequest, $newResponse);
        
        $this->mediator->notify(self::EVENT_REDIRECT, $requestKey, $oldLocation, $newLocation);
    }
    
    /**
     * Make multiple HTTP requests in parallel
     * 
     * @param mixed $requests An array, StdClass or Traversable list of requests
     * @throws Streams\StreamException
     * @throws TypeException
     * @return Http\MultiResponse
     */
    public function sendMulti($requests) {
        $this->validateRequestList($requests);
        $this->buildRequestMaps($requests);
        $this->execute();
        
        $responses = $this->requestKeyOrder;
        
        foreach ($this->requestKeyMap as $request) {
            $key = $this->requestKeyMap->getInfo();
            $responses[$key] = $this->requestResponseMap->offsetGet($request);
        }
        
        return new MultiResponse($responses);
    }
    
    /**
     * @param mixed $requests An array, StdClass or Traversable object
     * @return void
     * @throws TypeException
     */
    private function validateRequestList($requests) {
        if (!(is_array($requests)
            || $requests instanceof Traversable
            || $requests instanceof StdClass
        )) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new TypeException(
                get_class($this) . '::sendMulti expects an array, StdClass or Traversable object ' .
                "at Argument 1; $type provided"
            );
        }
        
        $count = 0;
        foreach ($requests as $request) {
            if (!$request instanceof Request) {
                $type = is_object($request) ? get_class($requests) : gettype($request);
                throw new TypeException(
                    get_class($this) . '::sendMulti requires that each element of the list passed ' .
                    'to Argument 1 implement Artax\\Http\\Request; ' . $type . ' provided'
                );
            }
            ++$count;
        }
        
        // This test may seem redundant but the standard empty() or count() checks will not work on
        // a StdClass. We use the generated $count value to verify that the list isn't empty.
        if (!$count) {
            throw new TypeException(
                get_class($this) . '::sendMulti requires a non-empty array, StdClass or ' .
                'Traversable request list at Argument 1'
            );
        }
    }
}