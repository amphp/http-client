<?php

namespace Artax;

use Exception,
    StdClass,
    RuntimeException,
    Traversable,
    SplObjectStorage,
    Spl\Mediator,
    Spl\TypeException,
    Spl\ValueException,
    Artax\Streams\Stream,
    Artax\Streams\SocketStream,
    Artax\Streams\IoException,
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
    const ATTR_THROW_ON_ERROR_STATUS = 130;
    const ATTR_AUTO_REFERER_ON_FOLLOW = 135;
    
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
    
    const EVENT_ERROR = 'artax.client.error';
    const EVENT_REDIRECT = 'artax.client.redirect';
    const EVENT_REQUEST = 'artax.client.request';
    const EVENT_RESPONSE = 'artax.client.response';
    const EVENT_STREAM_CHECKOUT = 'artax.client.conn.checkout';
    const EVENT_STREAM_CHECKIN = 'artax.client.conn.checkin';
    
    /**
     * @var bool
     */
    private $useKeepAlives = TRUE;
    
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
    private $throwOnErrorStatus = FALSE;
    
    /**
     * @var bool
     */
    private $autoRefererOnFollow = TRUE;
    
    /**
     * @var bool
     */
    private $sslVerifyPeer = TRUE;
    
    /**
     * @var bool
     */
    private $sslAllowSelfSigned = FALSE;
    
    /**
     * @var string
     */
    private $sslCertAuthorityFile = ARTAX_CERT_AUTHORITY;
    
    /**
     * @var string
     */
    private $sslCertAuthorityDirPath;
    
    /**
     * @var string
     */
    private $sslLocalCertFile;
    
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
     * @var SplObjectStorage
     */
    private $requestStateMap;
    
    /**
     * @var array
     */
    private $streamIdRequestMap;
    
    /**
     * @var Mediator
     */
    private $mediator;

    /**
     * @param \Spl\Mediator $mediator
     * @return \Artax\Client
     */
    public function __construct(Mediator $mediator) {
        $this->mediator = $mediator;
    }
    
    /**
     * Assign multiple Client attributes at once
     * 
     * @param mixed $arrayOrTraversable
     * @return void
     * @throws ValueException On invalid attribute constant
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
     * @throws ValueException On invalid attribute constant
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
            case self::ATTR_THROW_ON_ERROR_STATUS:
                $this->setThrowOnErrorStatus($val);
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
    protected function setKeepAlives($boolFlag) {
        $this->useKeepAlives = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param int $secondsUntilTimeout
     * @return void
     */
    protected function setConnectTimeout($secondsUntilTimeout) {
        $this->connectTimeout = (int) $secondsUntilTimeout;
    }
    
    /**
     * @param int $maxConnections
     * @return void
     */
    protected function setHostConcurrencyLimit($maxConnections) {
        $maxConnections = (int) $maxConnections;
        $maxConnections = $maxConnections < 1 ? 1 : $maxConnections;
        $this->hostConcurrencyLimit = $maxConnections;
    }
    
    /**
     * @param int $bytes
     * @return void
     */
    protected function setIoBufferSize($bytes) {
        $bytes = (int) $bytes;
        $this->ioBufferSize = $bytes <= 0 ? self::ATTR_IO_BUFFER_SIZE : $bytes;
    }
    
    /**
     * @param int $bitmask
     * @return void
     */
    protected function setFollowLocation($bitmask) {
        $this->followLocation = (int) $bitmask;
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    protected function setThrowOnErrorStatus($boolFlag) {
        $this->throwOnErrorStatus = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    protected function setAutoRefererOnFollow($boolFlag) {
        $this->autoRefererOnFollow = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    protected function setSslVerifyPeer($boolFlag) {
        $this->sslVerifyPeer = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param bool $boolFlag
     * @return void
     */
    protected function setSslAllowSelfSigned($boolFlag) {
        $this->sslAllowSelfSigned = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * @param string $certAuthorityFilePath
     * @return void
     */
    protected function setSslCertAuthorityFile($certAuthorityFilePath) {
        $this->sslCertAuthorityFile = $certAuthorityFilePath;
    }
    
    /**
     * @param string $certAuthorityDirPath
     * @return void
     */
    protected function setSslCertAuthorityDirPath($certAuthorityDirPath) {
        $this->sslCertAuthorityDirPath = $certAuthorityDirPath;
    }
    
    /**
     * @param string $localCertFilePath
     * @return void
     */
    protected function setSslLocalCertFile($localCertFilePath) {
        $this->sslLocalCertFile = $localCertFilePath;
    }
    
    /**
     * @param string $localCertPassphrase
     * @return void
     */
    protected function setSslLocalCertPassphrase($localCertPassphrase) {
        $this->sslLocalCertPassphrase = $localCertPassphrase;
    }
    
    /**
     * @param string $commonNameMatch
     * @return void
     */
    protected function setSslCommonNameMatch($commonNameMatch) {
        $this->sslCommonNameMatch = $commonNameMatch;
    }
    
    /**
     * @param int $depth
     * @return void
     */
    protected function setSslVerifyDepth($depth) {
        $this->sslVerifyDepth = (int) $depth;
    }
    
    /**
     * @param string $cipherList
     * @return void
     */
    protected function setSslCiphers($cipherList) {
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
        $this->mapRequestStates(array($request));
        $this->executeHttpRequests();
        
        $this->requestStateMap->rewind();
        $s = $this->requestStateMap->getInfo();
        
        if (!empty($s->error)) {
            throw new ClientException($s->error->getMessage(), 0, $s->error);
        } else {
            return $s->response;
        }
    }
    
    /**
     * Make multiple HTTP requests in parallel
     * 
     * @param mixed $requests An array, StdClass or Traversable list of requests
     * @return MultiResponse
     */
    public function sendMulti($requests) {
        $this->validateRequestTraversable($requests);
        $this->mapRequestStates($requests);
        $this->executeHttpRequests();
        
        $responses = array();
        $stateMap = $this->requestStateMap;
        for ($stateMap->rewind(); $stateMap->valid(); $stateMap->next()) {
            $s = $stateMap->getInfo();
            $responses[$s->key] = $s->error ?: $s->response;
        }
        
        return new MultiResponse($responses);
    }
    
    /**
     * @param mixed $requests An array, StdClass or Traversable object
     * @return void
     * @throws TypeException
     */
    protected function validateRequestTraversable($requests) {
        if (!(is_array($requests)
            || $requests instanceof Traversable
            || $requests instanceof StdClass
        )) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new TypeException(
                get_class($this) . ':sendMulti expects an array, StdClass or Traversable object ' .
                "at Argument 1; $type provided"
            );
        }
        
        $count = 0;
        foreach ($requests as $request) {
            if (!$request instanceof Request) {
                $type = is_object($request) ? get_class($requests) : gettype($request);
                throw new TypeException(
                    "Client::sendMulti requires that each element of the list passed to Argument " .
                    "1 implement Artax\\Http\\Request; $type provided"
                );
            }
            ++$count;
        }
        
        // This test may seem redundant but an empty() or count() check will not work on a StdClass
        if (!$count) {
            throw new TypeException(
                "Client::sendMulti requires a non-empty array, StdClass or Traversable request " .
                "list at Argument 1"
            );
        }
    }
    
    /**
     * @param mixed $requests
     * @return array
     */
    protected function mapRequestStates($requests) {
        $this->requestStateMap = new SplObjectStorage();
        $this->streamIdRequestMap = array();
        
        foreach ($requests as $key => $request) {
            $this->normalizeRequestHeaders($request);
            $this->mediator->notify(self::EVENT_REQUEST, $request);
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
        $s->stream = NULL;
        $s->error = NULL;
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
     * @param Http\Request $request
     * @return void
     */
    protected function normalizeRequestHeaders(Request $request) {
        $request->setHeader('User-Agent', self::USER_AGENT);
        $request->setHeader('Host', $request->getAuthority());
        
        if ($request->getBodyStream()) {
            $request->setHeader('Transfer-Encoding', 'chunked');
        } elseif ($request->allowsEntityBody() && $entityBody = $request->getBody()) {
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
     * @param StdClass $s
     * @return bool
     */
    protected function assignStreamToRequestState(Request $request, StdClass $s) {
        try {
            if ($stream = $this->checkoutStream($request)) {
                $s->stream = $stream;
                $s->state = ClientState::SENDING_REQUEST_HEADERS;
                $this->streamIdRequestMap[(int) $stream->getResource()] = $request;
                return TRUE;
            }
        } catch (ConnectException $e) {
            $this->markStateCompleteWithError($s, $e);
        }
        return FALSE;
    }
    
    /**
     * @param StdClass $s
     * @param Exception $e
     * @return void
     */
    protected function markStateCompleteWithError(StdClass $s, Exception $e) {
        $s->state = ClientState::ERROR;
        $s->error = $e;
        $this->mediator->notify(self::EVENT_ERROR, $s->key, $e);
    }
    
    /**
     * @return void
     */
    protected function executeHttpRequests() {
        while (!$this->hasCompletedAllRequests()) {
        
            list($read, $write) = $this->getSelectableIoStreams();
            if (!($read || $write)) {
                continue;
            }
            
            list($result, $read, $write) = $this->doStreamSelect($read, $write);
            if (empty($result)) {
                continue;
            }
            
            foreach ($write as $stream) {
                $request = $this->streamIdRequestMap[(int) $stream];
                try {
                    $this->writeRequest($request);
                } catch (Exception $e) {
                    $s = $this->requestStateMap->offsetGet($request);
                    $this->markStateCompleteWithError($s, $e);
                }
            }
            
            foreach ($read as $stream) {
                $request = $this->streamIdRequestMap[(int) $stream];
                try {
                    $this->readResponse($request);
                } catch (Exception $e) {
                    $s = $this->requestStateMap->offsetGet($request);
                    $this->markStateCompleteWithError($s, $e);
                }
            }
        }
    }
    
    /**
     * @return bool
     */
    protected function hasCompletedAllRequests() {
        $completedCount = 0;
        $stateMap = $this->requestStateMap;
        for ($stateMap->rewind(); $stateMap->valid(); $stateMap->next()) {
            $s = $stateMap->getInfo();
            $completedCount += $s->state >= ClientState::RESPONSE_RECEIVED;
        }
        return $completedCount == count($this->requestStateMap);
    }
    
    /**
     * @return array
     */
    protected function getSelectableIoStreams() {
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
            
            $stream = $s->stream->getResource();
            $streamKey = (int) $stream;
            
            if ($s->state < ClientState::READING_HEADERS) {
                $write[$streamKey] = $stream;
            } elseif ($s->state < ClientState::RESPONSE_RECEIVED) {
                $read[$streamKey] = $s->stream->getResource();
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
     * containing the select results as well as the read and write arrays of actionable IO streams.
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
        array $ex = array(),
        $tvsec = 3,
        $tvusec = 0
    ) {
        $result = @stream_select($read, $write, $ex, $tvsec, $tvusec);
        return array($result, $read, $write);
    }
    
    /**
     * @param Http\Request $request
     * @return Streams\Stream Returns null if max concurrency limit already reached
     * @throws Streams\ConnectException
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
                $this->sockPool[$socketUriString]->setInfo(TRUE);
                $this->mediator->notify(self::EVENT_STREAM_CHECKOUT, $stream);
                return $stream;
            }
        }
        
        $openHostStreams = count($this->sockPool[$socketUriString]);
        
        if ($this->hostConcurrencyLimit > $openHostStreams) {
            $stream = $this->makeStream($socketUri);
            
            if (0 === strcmp('tls', $socketUri->getScheme())) {
                $contextOpts = $this->buildSslContextOptionArray($socketUri);
            } else {
                $contextOpts = array();
            }
            
            $stream->connect(NULL, $this->connectTimeout, $contextOpts);
            stream_set_blocking($stream->getResource(), 0);
            
            $this->sockPool[$socketUriString]->attach($stream, TRUE);
            $this->mediator->notify(self::EVENT_STREAM_CHECKOUT, $stream);
            
            return $stream;
        } else {
            return NULL;
        }
    }

    /**
     * @param Http\Request $request
     * @throws ValueException
     * @return Uri
     */
    protected function buildSocketUriFromRequest(Request $request) {
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
        return new SocketStream($this->mediator, $socketUri);
    }

    /**
     * @param Uri $socketUri
     * @return array
     */
    protected function buildSslContextOptionArray(Uri $socketUri) {
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
     * @param Streams\Stream $stream
     * @return void
     */
    protected function checkinStream(Stream $stream) {
        $socketUriString = $stream->getUri();
        $this->sockPool[$socketUriString]->attach($stream, FALSE);
        $this->mediator->notify(self::EVENT_STREAM_CHECKIN, $stream);
    }
    
    /**
     * @param Streams\Stream $stream
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
     * @param Http\Request $request
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
     * @param Http\Request $request
     * @param StdClass $s
     * @throws Streams\IoException
     * @return void
     */
    protected function writeRequestHeaders(Request $request, StdClass $s) {
        $rawHeaders = $this->buildRawRequestHeaders($request);
        $rawHeaderBytes = strlen($rawHeaders);
        
        if ($s->headerBytesSent < $rawHeaderBytes) {
            $dataToSend = substr($rawHeaders, $s->headerBytesSent);
            if ($bytesSent = $s->stream->write($dataToSend)) {
                $s->headerBytesSent += $bytesSent;
            } elseif (FALSE === $bytesSent) {
                throw new IoException(
                    'Transfer failure: connection to ' . $s->stream->getHost() . ' lost after ' .
                    "{$s->headerBytesSent} header bytes sent"
                );
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
     * @param Http\Request $request
     * @return string
     */
    protected function buildRawRequestHeaders(Request $request) {
        return $request->getRequestLine() . "\r\n" . $request->getRawHeaders() . "\r\n";
    }

    /**
     * @param Http\Request $request
     * @param StdClass $s
     * @throws Streams\IoException
     * @return void
     */
    protected function writeBufferedRequestBody(Request $request, StdClass $s) {
        $requestBody = $request->getBody();
        $requestBodyBytes = strlen($requestBody);
        
        if ($s->bodyBytesSent < $requestBodyBytes) {
            $dataToSend = substr($requestBody, $s->bodyBytesSent);
            
            if ($bytesSent = $s->stream->write($dataToSend)) {
                $s->bodyBytesSent += $bytesSent;
            } elseif (FALSE === $bytesSent) {
                throw new IoException();
            }
        }
        
        if ($s->bodyBytesSent >= $requestBodyBytes) {
            $s->state = ClientState::READING_HEADERS;
        }
    }

    /**
     * @param Http\Request $request
     * @param StdClass $s
     * @throws \RuntimeException
     * @throws Streams\IoException
     * @return void
     */
    protected function writeStreamingRequestBody(Request $request, StdClass $s) {
        $requestBody = $request->getBodyStream();
        
        fseek($requestBody, 0, SEEK_END);
        $requestBodyBytes = ftell($requestBody);
        rewind($requestBody);
        
        $bodyBuffer = NULL;
        
        while (TRUE) {
            if (is_null($bodyBuffer)) {
                $readData = @fread($requestBody, $this->ioBufferSize);
                if (FALSE === $readData) {
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
                    $bodyBuffer = NULL;
                }
            } elseif (FALSE === $bytesSent) {
                throw new IoException();
            }
        }
    }
    
    /**
     * @param Http\Request $request
     * @return void
     * @throws ResponseStatusException
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
        
        if ($this->shouldCloseConnectionOnCompletion($s->response)) {
            $this->closeStream($s->stream);
        } else {
            $this->checkinStream($s->stream);
        }
        
        if ($this->canRedirect($request, $s->response, $s->redirectHistory)) {
            $this->doRedirect($request, $s);
        } else {
            $this->mediator->notify(self::EVENT_RESPONSE, $s->key, $s->response);
        }
        
        if ($this->throwOnErrorStatus && $s->response->getStatusCode() >= 400) {
            throw new ResponseStatusException(
                $s->response->getStatusLine(),
                $s->response->getStatusCode()
            );
        }
    }
    
    /**
     * @param StdClass $s
     * @return bool
     */
    protected function readHeaders(StdClass $s) {
        while ($readData = $s->stream->read($this->ioBufferSize)) {
            $s->responseBuffer .= $readData;
            $s->responseBuffer = ltrim($s->responseBuffer);
            
            if (FALSE === ($bodyStartPos = strpos($s->responseBuffer, "\r\n\r\n"))) {
                continue;
            }
            
            $startLineAndHeaders = substr($s->responseBuffer, 0, $bodyStartPos);
            list($startLine, $headers) = explode("\r\n", $startLineAndHeaders, 2);
            
            $s->responseBuffer = substr($s->responseBuffer, $bodyStartPos + 4);
            
            $s->response->setStartLine($startLine);
            $s->response->setAllRawHeaders($headers);
            
            if (!$this->responseAllowsEntityBody($s->response)) {
                $s->state = ClientState::RESPONSE_RECEIVED;
            } elseif ($this->isChunked($s->response)) {
                $s->state = ClientState::READING_CHUNKS;
            } elseif ($s->response->hasHeader('Content-Length')) {
                $s->state = ClientState::READING_UNTIL_LENGTH_REACHED;
            } else {
                $s->state = ClientState::READING_UNTIL_CLOSE;
            }
            
            return TRUE;
        }
        
        if (FALSE === $readData) {
            throw new IoException(
                'Transfer failure: connection to ' . $s->stream->getHost() . ' lost before ' .
                'headers were fully received'
            );
        }
        
        return FALSE;
    }
    
    /**
     * @param Http\Response $response
     * @return bool
     */
    protected function responseAllowsEntityBody(Response $response) {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode == 204) {
            return FALSE;
        }
        if ($statusCode == 304) {
            return FALSE;
        }
        if ($statusCode >= 100 && $statusCode < 200) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    /**
     * @param Http\Response $response
     * @return bool
     */
    protected function isChunked(Response $response) {
        if (!$response->hasHeader('Transfer-Encoding')) {
            return FALSE;
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
     * @throws \RuntimeException
     * @throws Streams\IoException
     * @return bool
     */
    protected function readBody(StdClass $s) {
        if (!$responseBodyStream = $s->response->getBodyStream()) {
            $responseBodyStream = $this->openMemoryStream();
            $s->response->setBody($responseBodyStream);
            if (FALSE === @fwrite($responseBodyStream, $s->responseBuffer)) {
                throw new RuntimeException("Failed writing to memory stream $responseBodyStream");
            }
        }
        
        while ($readData = $s->stream->read($this->ioBufferSize)) {
            if (FALSE === @fwrite($responseBodyStream, $readData)) {
                throw new RuntimeException("Failed writing to memory stream $responseBodyStream");
            }
        }
        
        if ($s->state == ClientState::READING_UNTIL_LENGTH_REACHED) {
            $bytesRecd = ftell($responseBodyStream);
            if ($bytesRecd >= $s->response->getHeader('Content-Length')) {
                $s->state = ClientState::RESPONSE_RECEIVED;
            }
        } elseif ($s->state == ClientState::READING_CHUNKS) {
            fseek($responseBodyStream, -1024, SEEK_END);
            if (preg_match(",\r\n0+\r\n\r\n$,", stream_get_contents($responseBodyStream), $m)) {
                stream_filter_prepend($responseBodyStream, 'dechunk');
                $s->state = ClientState::RESPONSE_RECEIVED;
            }
        } elseif ($s->state == ClientState::READING_UNTIL_CLOSE && $readData === '') {
            $s->state = ClientState::RESPONSE_RECEIVED;
        }
        
        if ($s->state == ClientState::RESPONSE_RECEIVED) {
            rewind($responseBodyStream);
            return TRUE;
        }
        
        if (FALSE === $readData) {
            throw new IoException(
                'Transfer failure: connection to ' . $s->stream->getHost() . ' lost after ' .
                ftell($responseBodyStream) . ' entity body bytes read'
            );
        }
        
        return FALSE;
    }
    
    /**
     * @param Http\Response $response
     * @return bool
     */
    protected function shouldCloseConnectionOnCompletion(Response $response) {
        if (!$this->useKeepAlives) {
            return TRUE;
        }
        if (!$response->hasHeader('Connection') && $response->getHttpVersion() == '1.1') {
            return FALSE;
        }
        if (strcmp($response->getHeader('Connection'), 'close')) {
            return FALSE;
        }
        return TRUE;
    }
    
    /**
     * @return resource
     * @throws RuntimeException
     */
    protected function openMemoryStream() {
        $stream = @fopen('php://temp', 'r+');
        
        if (FALSE !== $stream) {
            return $stream;
        } else {
            throw new RuntimeException(
                'Failed opening in-memory stream'
            );
        }
    }
    
    /**
     * @param Http\Request $request
     * @param Http\Response $response
     * @param array $redirectHistory
     * @return bool
     */
    protected function canRedirect(Request $request, Response $response, array $redirectHistory) {
        if ($this->followLocation == self::FOLLOW_LOCATION_NONE) {
            return FALSE;
        }
        
        if (!$response->hasHeader('Location')) {
            return FALSE;
        }
        
        $this->normalizeRedirectLocation($request, $response);
        
        $statusCode = $response->getStatusCode();
        
        $canFollow3xx = self::FOLLOW_LOCATION_ON_3XX;
        if ($statusCode >= 300
            && $statusCode < 400
            && !(($this->followLocation | $canFollow3xx) == $this->followLocation)
        ) {
           return FALSE;
        }
        
        $canFollow2xx = self::FOLLOW_LOCATION_ON_2XX;
        if ($statusCode >= 200
            && $statusCode < 300
            && !(($this->followLocation | $canFollow2xx) == $this->followLocation)
        ) {
           return FALSE;
        }
        
        $requestMethod = $request->getMethod();
        $canFollowUnsafe = self::FOLLOW_LOCATION_ON_UNSAFE_METHOD;
        if (!in_array($requestMethod, array('GET', 'HEAD'))
            && !(($this->followLocation | $canFollowUnsafe) == $this->followLocation)
        ) {
            return FALSE;
        }
        
        return TRUE;
    }
    
    /**
     * @param Http\Request $request
     * @param StdClass $s
     * @return void
     */
    protected function doRedirect(Request $request, StdClass $s) {
        $oldLocation = $request->getUri();
        $newLocation = $s->response->getHeader('Location');
        
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
        
        if ($this->autoRefererOnFollow) {
            $newRequest->setHeader('Referer', $oldLocation);
        }
        
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
     * @param Http\Request $prevRequest
     * @param Http\Response $prevResponse
     * @return void
     */
    protected function normalizeRedirectLocation(Request $prevRequest, Response $prevResponse) {
        $locationHeader = $prevResponse->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $prevRequest->getScheme() . '://' . $prevRequest->getAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $prevResponse->setHeader('Location', $newLocation);
            $prevResponse->appendHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
        }
    }
}