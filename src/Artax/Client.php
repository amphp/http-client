<?php

namespace Artax;

use Exception,
    Traversable,
    SplObjectStorage,
    Spl\Mediator,
    Spl\KeyException,
    Spl\TypeException,
    Spl\DomainException,
    Artax\Uri,
    Artax\Http\Request,
    Artax\Http\StdRequest,
    Artax\Http\RequestWriter,
    Artax\Http\StdResponse,
    Artax\Http\Parsing\ResponseParser,
    Artax\Http\Parsing\ParseException;

class Client {
    
    const USER_AGENT = 'Artax/1.0.0 (PHP5.3+)';
    
    const ATTR_KEEP_CONNS_ALIVE = 'attrKeepConnsAlive';
    const ATTR_CONNECT_TIMEOUT = 'attrConnectTimeout';
    const ATTR_TRANSFER_TIMEOUT = 'attrTransferTimeout';
    const ATTR_IDLE_TRANSFER_TIMEOUT = 'attrIdleTransferTimeout';
    const ATTR_FOLLOW_LOCATION = 'attrFollowLocation';
    const ATTR_AUTO_REFERER_ON_FOLLOW = 'attrAutoRefererOnFollow';
    const ATTR_CONCURRENCY_LIMIT = 'attrConcurrencyLimit';
    const ATTR_HOST_CONCURRENCY_LIMIT = 'attrHostConcurrencyLimit';
    const ATTR_CLOSE_POLICY = 'attrClosePolicy';
    const ATTR_IO_BUFFER_SIZE = 'attrIoBufferSize';
    const ATTR_100_CONTINUE_DELAY = 'attr100ContinueDelay';
    const ATTR_IGNORE_BODY = 'attrIgnoreBody';
    const ATTR_BUFFER_BODY = 'attrBufferBody';
    const ATTR_VERBOSE = 'attrVerbose';
    
    const ATTR_SSL_VERIFY_PEER = 'attrSslVerifyPeer';
    const ATTR_SSL_ALLOW_SELF_SIGNED = 'attrSslAllowSelfSigned';
    const ATTR_SSL_CA_FILE = 'attrSslCertAuthorityFile';
    const ATTR_SSL_CA_PATH = 'attrSslCertAuthorityDirPath';
    const ATTR_SSL_LOCAL_CERT = 'attrSslLocalCertFile';
    const ATTR_SSL_LOCAL_CERT_PASSPHRASE = 'attrSslLocalCertPassphrase';
    const ATTR_SSL_CN_MATCH = 'attrSslCommonNameMatch';
    const ATTR_SSL_VERIFY_DEPTH = 'attrSslVerifyDepth';
    const ATTR_SSL_CIPHERS = 'attrSslCiphers';
    
    const FOLLOW_LOCATION_NONE = 0;
    const FOLLOW_LOCATION_ON_3XX = 1;
    const FOLLOW_LOCATION_ON_2XX = 2;
    const FOLLOW_LOCATION_ON_UNSAFE_METHOD = 4;
    const FOLLOW_LOCATION_ALL = 7;
    
    const CLOSE_POLICY_LEAST_RECENTLY_USED = 1;
    const CLOSE_POLICY_OLDEST = 2;
    const CLOSE_POLICY_LEAST_TRAFFIC = 4;
    const CLOSE_POLICY_SLOWEST_AVG_DL_SPEED = 8;
    
    const STATE_NEEDS_SOCKET = 0;
    const STATE_SOCKET_PENDING = 1;
    const STATE_WRITING = 2;
    const STATE_CONTINUE = 4;
    const STATE_READING = 16;
    const STATE_COMPLETE = 32;
    const STATE_ERROR = 64;
    
    const EVENT_REQUEST = 'artax.client.request';
    const EVENT_WRITE = 'artax.client.write';
    const EVENT_READ = 'artax.client.read';
    const EVENT_REDIRECT = 'artax.client.redirect';
    const EVENT_RESPONSE = 'artax.client.response';
    const EVENT_ERROR = 'artax.client.error';
    
    /**
     * A non-fatal socket connect error indicating that the socket is still connecting and it's safe
     * to store the resulting resource in the "pending" socket pool.
     * 
     * @var int
     */
    const SOCK_EWOULDBLOCK = 10035;
    
    /**
     * @var array
     */
    private $attributes = array(
        self::ATTR_KEEP_CONNS_ALIVE => true,
        self::ATTR_CONNECT_TIMEOUT => 5,
        self::ATTR_TRANSFER_TIMEOUT => 0,
        self::ATTR_IDLE_TRANSFER_TIMEOUT => 0,
        self::ATTR_FOLLOW_LOCATION => self::FOLLOW_LOCATION_ON_3XX,
        self::ATTR_AUTO_REFERER_ON_FOLLOW => true,
        self::ATTR_CONCURRENCY_LIMIT => 25,
        self::ATTR_HOST_CONCURRENCY_LIMIT => 3,
        self::ATTR_CLOSE_POLICY => self::CLOSE_POLICY_LEAST_RECENTLY_USED,
        self::ATTR_IO_BUFFER_SIZE => 8192,
        self::ATTR_100_CONTINUE_DELAY => 3,
        self::ATTR_IGNORE_BODY => false,
        self::ATTR_BUFFER_BODY => true,
        self::ATTR_VERBOSE => false,
        
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
    
    private $requestWriterFactory;
    private $responseParserFactory;
    private $mediator;
    
    private $requestWriters = array();
    private $responseParsers = array();
    private $requestWriterKeyMap;
    private $responseParserKeyMap;
    
    private $requestKeys;
    private $requestUris;
    private $requests;
    private $responses;
    private $errors;
    private $states;
    
    private $requestStats;
    
    private $socketPool = array();
    private $pendingSockets = array();
    private $socketIdRequestMap;
    private $socketIdRequestKeyMap;
    private $requestKeySocketMap;
    
    private $isInMultiMode;
    
    /**
     * @param RequestWriterFactory $requestWriterFactory
     * @param ResponseParserFactory $responseParserFactory
     * @param \Spl\Mediator $mediator
     */
    public function __construct(
        RequestWriterFactory $requestWriterFactory,
        ResponseParserFactory $responseParserFactory,
        Mediator $mediator
    ) {
        $this->requestWriterFactory = $requestWriterFactory;
        $this->responseParserFactory = $responseParserFactory;
        $this->mediator = $mediator;
        
        $mediator->addListener(RequestWriter::EVENT_WRITE, array($this, 'onWrite'));
        $mediator->addListener(ResponseParser::EVENT_READ, array($this, 'onRead'));
    }
    
    /**
     * Make an HTTP request
     * 
     * @param mixed $request A request URI or an instance implementing Artax\Http\Request
     * @throws ClientException On connection failure, socket error or invalid response
     * @return ClientResult The HTTP response
     */
    public function send($requestOrUri) {
        $this->isInMultiMode = false;
        $this->buildRequestMaps(array($requestOrUri));
        $this->execute();
        
        return $this->buildClientResult(0);
    }
    
    /**
     * Make multiple HTTP requests in parallel
     * 
     * @param mixed $requests An array or Traversable list of request instances or URIs
     * @throws \Spl\TypeException On invalid or empty request list
     * @return ClientMultiResponse
     */
    public function sendMulti($requests) {
        $this->isInMultiMode = true;
        $this->validateRequestList($requests);
        $this->buildRequestMaps($requests);
        $this->execute();
        
        $multiResult = array();
        
        foreach ($this->requestKeys as $requestKey) {
            if (isset($this->errors[$requestKey])) {
                $multiResult[$requestKey] = null;
            } else {
                $multiResult[$requestKey] = $this->buildClientResult($requestKey);
            }
        }
        
        return new ClientMultiResult($multiResult, $this->errors);
    }
    
    private function validateRequestList($requests) {
        if (!($requests instanceof Traversable || is_array($requests))) {
            $type = is_object($requests) ? get_class($requests) : gettype($requests);
            throw new TypeException(
                get_class($this) . '::sendMulti expects an array or Traversable object ' .
                "at Argument 1; $type provided"
            );
        } elseif (!count($requests)) {
            throw new TypeException(
                'No requests specified'
            );
        }
    }
    
    private function buildRequestMaps($requests) {
        $this->requestKeys = array();
        $this->requestUris = array();
        $this->requests = array();
        $this->responses = array();
        $this->errors = array();
        $this->states = array();
        
        $this->requestKeySocketMap = array();
        $this->socketIdRequestKeyMap = array();
        $this->socketIdRequestMap = array();
        
        $this->requestWriterKeyMap = new SplObjectStorage;
        $this->responseParserKeyMap = new SplObjectStorage;
        
        $this->requestStats = array();
        
        foreach ($requests as $requestKey => $inputRequest) {
            $this->requestKeys[] = $requestKey;
            $this->responses[$requestKey] = array();
            $this->states[$requestKey] = self::STATE_NEEDS_SOCKET;
            $this->initializeRequestStats($requestKey);
            
            $inputRequest = $this->prepRequestForSend($requestKey, $inputRequest);
            $this->mediator->notify(self::EVENT_REQUEST, $requestKey, $inputRequest);
            $this->requests[$requestKey] = $inputRequest;
        }
    }
    
    private function initializeRequestStats($requestKey) {
        $this->requestStats[$requestKey] = array(
            'connectedAt' => null,
            'firstSentAt' => null,
            'lastSentAt' => null,
            'firstRecdAt' => null,
            'lastRecdAt' => null,
            'bytesSent' => null,
            'bytesRecd' => null,
            'avgUpKbps' => null,
            'avgDownKbps' => null
        );
    }
    
    private function prepRequestForSend($requestKey, $inputRequest) {
        $request = new StdRequest();
        
        if (is_string($inputRequest)) {
            $request->setUri($inputRequest);
        } elseif ($inputRequest instanceof Request) {
            $request->import($inputRequest);
        } else {
            throw new TypeException(
                'URI string or Request instance required'
            );
        }
        
        $uri = new Uri($request->getUri());
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        
        if (!($scheme == 'http' || $scheme == 'https')) {
            throw new DomainException(
                'Request URI must specify a scheme of http or https'
            );
        } elseif (empty($host)) {
            throw new DomainException(
                'Request URI must specify a host component'
            );
        }
        
        $this->requestUris[$requestKey] = $uri;
        
        $request->setHeader('Host', $uri->getAuthority());
        
        if (!$request->hasHeader('User-Agent')) {
            $request->setHeader('User-Agent', self::USER_AGENT);
        }
        
        if (!$request->getProtocol()) {
            $request->setProtocol(1.1);
        }
        
        $method = $request->getMethod();
        if (empty($method)) {
            $request->setMethod(Request::GET);
        } elseif ($method == Request::TRACE) {
            $request->setBody(null);
        }
        
        $body = $request->getBody();
        $bodyIsResource = is_resource($body);
        $allowsChunkedEncoding = (version_compare($request->getProtocol(), 1.1) >= 0);
        
        if (empty($body) && $body !== '0') {
            $request->removeHeader('Content-Length');
            $request->removeHeader('Transfer-Encoding');
        } elseif ($bodyIsResource && $allowsChunkedEncoding) {
            $request->removeHeader('Content-Length');
            $request->setHeader('Transfer-Encoding', 'chunked');
        } elseif ($bodyIsResource) {
            $currentBodyPos = ftell($body);
            fseek($body, 0, SEEK_END);
            $endBodyPos = ftell($body);
            fseek($body, $currentBodyPos);
            $request->setHeader('Content-Length', $endBodyPos);
            $request->removeHeader('Transfer-Encoding');
        } else {
            $request->setHeader('Content-Length', strlen($body));
            $request->removeHeader('Transfer-Encoding');
        }
        
        $request->removeHeader('Accept-Encoding');
        
        if (!$this->attributes[self::ATTR_KEEP_CONNS_ALIVE]) {
            $request->setHeader('Connection', 'close');
        }
        
        // Connections must be closed after headers are read when the entity body is "artificially"
        // ignored by the client or parsing errors will occur on future requests to the pooled
        // persistent connection.
        if ($this->attributes[self::ATTR_IGNORE_BODY]
            && $request->getMethod() != Request::HEAD
        ) {
            $request->setHeader('Connection', 'close');
        }
        
        return $request;
    }
    
    private function execute() {
        while ($incompleteRequestKeys = $this->getIncompleteRequestKeys()) {
            $this->assignRequestSockets();
            
            if (!$selectables = $this->getSelectableStreams($incompleteRequestKeys)) {
                continue;
            }
            
            $read = $write = $selectables;
            if (!@stream_select($read, $write, $ex = null, 3, 0)) {
                continue;
            }
            
            foreach ($write as $socket) {
                $socketId = (int) $socket;
                $requestKey = $this->socketIdRequestKeyMap[$socketId];
                
                if ($this->states[$requestKey] != self::STATE_READING) {
                    $this->doMultiSafeWrite($requestKey);
                }
            }
            
            foreach ($read as $socket) {
                $socketId = (int) $socket;
                $requestKey = $this->socketIdRequestKeyMap[$socketId];
                
                if ($this->states[$requestKey] == self::STATE_READING) {
                    $this->doMultiSafeRead($requestKey);
                }
            }
            
            if ($this->attributes[self::ATTR_TRANSFER_TIMEOUT]) {
                $this->timeoutTransfers();
            }
            
            if ($this->attributes[self::ATTR_IDLE_TRANSFER_TIMEOUT]) {
                $this->timeoutIdleTransfers();
            }
        }
    }
    
    private function timeoutTransfers() {
        $maxAllowedTransferTime = $this->attributes[self::ATTR_TRANSFER_TIMEOUT];
        $now = microtime(true);
        
        foreach ($this->requestKeys as $requestKey) {
            $state = $this->states[$requestKey];
            if ($state < self::STATE_WRITING || $state >= self::STATE_COMPLETE) {
                continue;
            }
            
            $transferTime = $now - $this->requestStats[$requestKey]['connectedAt'];
            
            if ($transferTime < $maxAllowedTransferTime) {
                continue;
            }
            
            $this->closeSocketByRequestKey($requestKey);
            
            $s = $maxAllowedTransferTime == 1 ? '' : 's';
            $timeoutException = new ClientException(
                "Transfer timeout exceeded: {$transferTime} seconds ({$maxAllowedTransferTime} " .
                "second{$s} allowed)"
            );
            
            if ($this->isInMultiMode) {
                $this->setError($requestKey, $timeoutException);
            } else  {
                throw $timeoutException;
            }
        }
    }
    
    private function timeoutIdleTransfers() {
        $maxAllowedIdleTime = $this->attributes[self::ATTR_IDLE_TRANSFER_TIMEOUT];
        $now = microtime(true);
        
        foreach ($this->requestKeys as $requestKey) {
            $state = $this->states[$requestKey];
            if ($state < self::STATE_WRITING || $state >= self::STATE_COMPLETE) {
                continue;
            }
            
            $stats = $this->requestStats[$requestKey];
            $lastActivity = max($stats['lastSentAt'], $stats['lastRecdAt']);
            $idleTime = $now - $lastActivity;
            
            if ($idleTime < $maxAllowedIdleTime) {
                continue;
            }
            
            $this->closeSocketByRequestKey($requestKey);
            
            $s = $maxAllowedIdleTime == 1 ? '' : 's';
            $timeoutException = new ClientException(
                "Idle transfer timeout exceeded: {$idleTime} seconds ({$maxAllowedIdleTime} " .
                "second{$s} allowed)"
            );
            
            if ($this->isInMultiMode) {
                $this->setError($requestKey, $timeoutException);
            } else  {
                throw $timeoutException;
            }
        }
    }
    
    private function doMultiSafeWrite($requestKey) {
        if ($this->isInMultiMode) {
            try {
                $this->write($requestKey);
            } catch (ClientException $e) {
                $this->setError($requestKey, $e);
            }
        } else {
            $this->write($requestKey);
        }
    }
    
    private function write($requestKey) {
        /**
         * @var \Artax\RequestWriter $requestWriter
         */
        $requestWriter = $this->requestWriters[$requestKey];
        
        try {
            $isWriteComplete = $requestWriter->send();
        } catch (DomainException $e) {
            throw new ClientException(
                'Socket connection lost prior to request write completion',
                0,
                $e
            );
        }
        
        if ($isWriteComplete) {
            $this->states[$requestKey] = self::STATE_READING;
            $this->assignResponseParser($requestKey);
        } elseif ($requestWriter->expectsContinue()
            && empty($this->responseParsers[$requestKey])
        ) {
            $this->states[$requestKey] = self::STATE_CONTINUE;
            $this->assignResponseParser($requestKey);
        }
    }
    
    private function assignResponseParser($requestKey) {
        $request = $this->requests[$requestKey];
        $socket = $this->requestKeySocketMap[$requestKey];
        
        $responseParser = $this->responseParserFactory->make($socket, $this->mediator);
        $this->responseParserKeyMap->attach($responseParser, $requestKey);
        
        $responseParser->setAttribute(ResponseParser::ATTR_STRICT, false);
        $responseParser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $responseParser->setAttribute(
            ResponseParser::ATTR_MAX_GRANULARITY,
            $this->attributes[self::ATTR_IO_BUFFER_SIZE]
        );
        
        if ($request->getMethod() == Request::HEAD || $this->attributes[self::ATTR_IGNORE_BODY]) {
            $responseParser->setAttribute(ResponseParser::ATTR_IGNORE_BODY, true);
        }
        
        $this->responseParsers[$requestKey] = $responseParser;
    }
    
    private function assignRequestWriter($requestKey, $socket) {
        $request = $this->requests[$requestKey];
        
        $requestWriter = $this->requestWriterFactory->make($request, $socket, $this->mediator);
        $this->requestWriterKeyMap->attach($requestWriter, $requestKey);
        
        $requestWriter->setAttribute(
            RequestWriter::ATTR_STREAM_BUFFER_SIZE,
            $this->attributes[self::ATTR_IO_BUFFER_SIZE]
        );
        
        $this->requestWriters[$requestKey] = $requestWriter;
    }
    
    private function doMultiSafeRead($requestKey) {
        if ($this->isInMultiMode) {
            try {
                $this->read($requestKey);
            } catch (ClientException $e) {
                $this->setError($requestKey, $e);
            }
        } else {
            $this->read($requestKey);
        }
    }
    
    private function read($requestKey) {
        /**
         * @var \Artax\Http\Parsing\ResponseParser $responseParser
         */
        $responseParser = $this->responseParsers[$requestKey];
        
        try {
            if (!$response = $responseParser->parse()) {
                return;
            }
        } catch (ParseException $e) {
            throw new ClientException(
                'Response message parse failure',
                0,
                $e
            );
        }
        
        if ($response->getStatusCode() == 100
            && $this->states[$requestKey] == self::STATE_CONTINUE
        ) {
            $requestWriter = $this->requestWriters[$requestKey];
            $requestWriter->allowContinuation();
            $this->states[$requestKey] = self::STATE_WRITING;
        } else {
            $request = $this->requests[$requestKey];
            $requestUri = $request->getUri();
            
            $this->responses[$requestKey][$requestUri] = $response;
            $this->finalizeResponse($requestKey, $response);
        }
    }
    
    private function enableMultiSafeSocketCrypto($requestKey, $socket) {
        if ($this->isInMultiMode) {
            try {
                return $this->enableSocketCrypto($socket);
            } catch (ClientException $e) {
                $this->setError($requestKey, $e);
                return false;
            }
        } else {
            return $this->enableSocketCrypto($socket);
        }
    }
    
    private function enableSocketCrypto($socket) {
        $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // A switch statement must not be used here because the strict 0/false difference matters
        if ($crypto) {
            return true;
        } elseif (0 === $crypto) {
            return false;
        } else {
            $e = error_get_last();
            throw new ClientException(
                'SSL connect failure: ' . $e['message'] .' in '. $e['file'] .' on line '. $e['line']
            );
        }
    }
    
    private function setError($requestKey, Exception $e) {
        $this->states[$requestKey] = self::STATE_ERROR;
        $this->errors[$requestKey] = $e;
        
        $this->mediator->notify(self::EVENT_ERROR, $requestKey, $e);
    }
    
    private function getIncompleteRequestKeys() {
        $incompleteRequestKeys = array();
        
        foreach ($this->requestKeys as $requestKey) {
            if ($this->states[$requestKey] < self::STATE_COMPLETE) {
                $incompleteRequestKeys[] = $requestKey;
            }
        }
        
        return $incompleteRequestKeys;
    }

    private function getSelectableStreams($incompleteRequestKeys) {
        $selectables = array();
        
        foreach ($incompleteRequestKeys as $requestKey) {
            $state = $this->states[$requestKey];
            if ($state > self::STATE_SOCKET_PENDING && $state < self::STATE_COMPLETE) {
                $selectables[] = $this->requestKeySocketMap[$requestKey];
            }
        }
        
        return $selectables;
    }
    
    private function assignRequestSockets() {
        foreach ($this->requestKeys as $requestKey) {
            if ($this->states[$requestKey] == self::STATE_NEEDS_SOCKET) {
                $this->doMultiSafeSocketCheckout($requestKey);
            }
            if (isset($this->pendingSockets[$requestKey])) {
                $this->validatePendingSocket($requestKey);
            }
        }
    }
    
    private function doMultiSafeSocketCheckout($requestKey) {
        if ($this->isInMultiMode) {
            try {
                $this->checkoutSocket($requestKey);
            } catch (ClientException $e) {
                $this->setError($requestKey, $e);
            }
        } else {
            $this->checkoutSocket($requestKey);
        }
    }
    
    private function checkoutSocket($requestKey) {
        /**
         * @var \Artax\Uri $requestUri
         */
        $requestUri = $this->requestUris[$requestKey];
        
        $isCrypto = ($requestUri->getScheme() == 'https');
        $scheme = 'tcp';
        $host = $requestUri->getHost();
        if (!$port = $requestUri->getPort()) {
            $port = $isCrypto ? 443 : 80;
        }
        
        $authority = "$host:$port";
        
        if ($socket = $this->doExistingSocketCheckout($authority)) {
            $socketId = (int) $socket;
            $this->states[$requestKey] = self::STATE_WRITING;
            $this->socketIdRequestKeyMap[$socketId] = $requestKey;
            $this->requestKeySocketMap[$requestKey] = $socket;
            $this->assignRequestWriter($requestKey, $socket);
            $this->requestStats[$requestKey]['connectedAt'] = microtime(true);
            
        } elseif (!$this->isNewConnectionToAuthorityAllowed($authority)) {
            return;
        } elseif (!$this->isNewConnectionAllowed()) {
            return;
        } elseif ($socket = $this->doNewSocketCheckout($isCrypto, $host, $port)) {
            $this->pendingSockets[$requestKey] = array(
                'socket' => $socket,
                'authority' => $authority,
                'activityAt' => microtime(true),
                'crypto' => $isCrypto
            );
            
            $this->states[$requestKey] = self::STATE_SOCKET_PENDING;
        }
    }
    
    private function doExistingSocketCheckout($socketAuthority) {
        foreach ($this->socketPool as $socketId => $socketArr) {
            
            // Is the socket already in use by another request?
            if (isset($this->socketIdRequestKeyMap[$socketId])) {
                continue;
            }
            
            // Is the existing socket connected to a different authority than the one we need?
            if ($socketArr['authority'] != $socketAuthority) {
                continue;
            }
            
            // Is the socket connection dead?
            $sock = $socketArr['socket']; 
            if (!is_resource($sock) || @feof($sock)) {
                @fclose($sock);
                unset($this->socketPool[$socketId]);
                continue;
            }
            
            // If we're still here we can checkout and use this socket
            return $socketArr['socket'];
        }
        
        return null;
    }
    
    private function isNewConnectionToAuthorityAllowed($socketAuthority) {
        $currentConns = 0;
        foreach ($this->socketPool as $socketArr) {
            $currentConns += ($socketArr['authority'] == $socketAuthority);
        }
        
        $allowedConns = $this->attributes[self::ATTR_HOST_CONCURRENCY_LIMIT];
        
        return ($allowedConns > $currentConns);
    }
    
    private function isNewConnectionAllowed() {
        $openAndPendingSockets = count($this->socketPool) + count($this->pendingSockets);
        
        if ($openAndPendingSockets < $this->attributes[self::ATTR_CONCURRENCY_LIMIT]) {
            return true;
        }
        
        $availableSockets = $this->getAvailableSockets();
        if (empty($availableSockets)) {
            return false;
        }
        
        switch ($this->attributes[self::ATTR_CLOSE_POLICY]) {
            case self::CLOSE_POLICY_LEAST_RECENTLY_USED:
                $this->closeLeastRecentlyUsedSocket($availableSockets);
                return true;
                break;
            case self::CLOSE_POLICY_OLDEST:
                $this->closeOldestSocket($availableSockets);
                return true;
                break;
            case self::CLOSE_POLICY_LEAST_TRAFFIC:
                $this->closeLeastTraffickedSocket($availableSockets);
                return true;
                break;
            case self::CLOSE_POLICY_SLOWEST_AVG_DL_SPEED:
                $this->closeSocketWithSlowestAverageDownloadSpeed($availableSockets);
                return true;
                break;
            default:
                // this should never happen but ...
                return false;
        }
    }
    
    private function getAvailableSockets() {
        $availableSockets = array();
        foreach ($this->socketPool as $sockArr) {
            $socketId = (int) $sockArr['socket'];
            if (!isset($this->socketIdRequestKeyMap[$socketId])) {
                $availableSockets[] = $sockArr;
            }
        }
        
        return $availableSockets;
    }
    
    private function closeOldestSocket(array $availableSockets) {
        uasort($availableSockets, function($a, $b) {
            if ($a['createdAt'] == $b['createdAt']) {
                return 0;
            } else {
                return ($a['createdAt'] > $b['createdAt']) ? -1 : 1;
            }
        });
        
        $sockToKill = end($availableSockets);
        $socketId = (int) $sockToKill['socket'];
        @fclose($sockToKill['socket']);
        unset($this->socketPool[$socketId]);
    }
    
    private function closeLeastRecentlyUsedSocket(array $availableSockets) {
        uasort($availableSockets, function($a, $b) {
            $a = $a['activityAt'];
            $b = $b['activityAt'];
            if ($a == $b) {
                return 0;
            } else {
                return ($a > $b) ? -1 : 1;
            }
        });
        
        $sockToKill = end($availableSockets);
        $socketId = (int) $sockToKill['socket'];
        @fclose($sockToKill['socket']);
        unset($this->socketPool[$socketId]);
    }
    
    private function closeLeastTraffickedSocket(array $availableSockets) {
        uasort($availableSockets, function($a, $b) {
            $a = $a['bytesSent'] + $a['bytesRecd'];
            $b = $b['bytesSent'] + $b['bytesRecd'];
            
            if ($a == $b) {
                return 0;
            } else {
                return ($a > $b) ? -1 : 1;
            }
        });
        
        $sockToKill = end($availableSockets);
        $socketId = (int) $sockToKill['socket'];
        @fclose($sockToKill['socket']);
        unset($this->socketPool[$socketId]);
    }
    
    private function closeSocketWithSlowestAverageDownloadSpeed(array $availableSockets) {
        uasort($availableSockets, function($a, $b) {
            $a = $a['bytesRecd'] / $a['totalDownloadingTime'];
            $b = $b['bytesRecd'] / $b['totalDownloadingTime'];
            
            if ($a == $b) {
                return 0;
            } else {
                return ($a < $b) ? -1 : 1;
            }
        });
        
        $sockToKill = end($availableSockets);
        $socketId = (int) $sockToKill['socket'];
        @fclose($sockToKill['socket']);
        unset($this->socketPool[$socketId]);
    }
    
    private function doNewSocketCheckout($isCrypto, $host, $port) {
        $context = $isCrypto ? $this->buildSslContext($host) : stream_context_create(array());
        
        list($socket, $errNo, $errStr) = $this->makeSocketStream("tcp://$host:$port", $context);
        
        // SOCK_EWOULDBLOCK means its trying really hard to connect and we should give it a moment
        if (false !== $socket || $errNo === self::SOCK_EWOULDBLOCK) {
            return $socket;
        } else {
            $errorMsg = "Socket connection failure: tcp://$host:$port";
            $errorMsg .= $errNo ? "; [Error# $errNo] $errStr" : '';
            
            throw new ClientException($errorMsg);
        }
    }
    
    private function buildSslContext($host) {
        $opts = array(
            'verify_peer' => $this->attributes[self::ATTR_SSL_VERIFY_PEER],
            'allow_self_signed' => $this->attributes[self::ATTR_SSL_ALLOW_SELF_SIGNED],
            'verify_depth' => $this->attributes[self::ATTR_SSL_VERIFY_DEPTH],
            'cafile' => $this->attributes[self::ATTR_SSL_CA_FILE],
            'CN_match' => $this->attributes[self::ATTR_SSL_CN_MATCH] ?: $host,
            'ciphers' => $this->attributes[self::ATTR_SSL_CIPHERS]
        );
        
        if ($caDirPath = $this->attributes[self::ATTR_SSL_CA_PATH]) {
            $opts['capath'] = $caDirPath;
        }
        if ($localCert = $this->attributes[self::ATTR_SSL_LOCAL_CERT]) {
            $opts['local_cert'] = $localCert;
        }
        if ($localCertPassphrase = $this->attributes[self::ATTR_SSL_LOCAL_CERT_PASSPHRASE]) {
            $opts['passphrase'] = $localCertPassphrase;
        }
       
        return stream_context_create(array('ssl' => $opts));
    }

    /**
     * IMPORTANT: this is a test seam allowing us to mock socket IO for testing.
     * 
     * @param string $uri
     * @param resource $context
     * @return array($resource, $errNo, $errStr)
     */
    protected function makeSocketStream($uri, $context) {
        $socket = @stream_socket_client(
            $uri,
            $errNo,
            $errStr,
            42, // <--- not used with ASYNC connections, so the value doesn't matter
            STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT,
            $context
        );
        
        // `stream_socket_client` modifies $errNo & $errStr by reference. Because we don't want to 
        // throw an exception here on connection failures we need to return these values along with
        // the $socket return value.
        return array($socket, $errNo, $errStr);
    }
    
    private function validatePendingSocket($requestKey) {
        $now = microtime(true);
        $connectTimeout = $this->attributes[self::ATTR_CONNECT_TIMEOUT];
        
        $pendingSocketArr = $this->pendingSockets[$requestKey];
        $socket = $pendingSocketArr['socket'];
        $needsCrypto = $pendingSocketArr['crypto'];
        
        $read = $ex = null;
        $write = array($socket);
        if (stream_select($read, $write, $ex, 0, 0)) {
            
            if ($needsCrypto && !$this->enableMultiSafeSocketCrypto($requestKey, $socket)) {
                return;
            }
            
            $now = microtime(true);
            $socketId = (int) $socket;
            stream_set_blocking($socket, 0);
            
            $pendingSocketArr['connectedAt'] = $now;
            $pendingSocketArr['bytesSent'] = 0;
            $pendingSocketArr['bytesRecd'] = 0;
            $pendingSocketArr['totalDownloadingTime'] = 0;
            
            $this->states[$requestKey] = self::STATE_WRITING;
            $this->socketIdRequestKeyMap[$socketId] = $requestKey;
            $this->requestKeySocketMap[$requestKey] = $socket;
            $this->socketPool[$socketId] = $pendingSocketArr;
            $this->assignRequestWriter($requestKey, $socket);
            $this->requestStats[$requestKey]['connectedAt'] = $now;
            
            unset($this->pendingSockets[$requestKey]);
        } else {
            $timeSpentPending = ($now - $pendingSocketArr['activityAt']);
            
            if ($timeSpentPending < $connectTimeout) {
                return;
            }
            
            @fclose($this->pendingSockets[$requestKey]);
            unset($this->pendingSockets[$requestKey]);
            
            if ($this->isInMultiMode) {
                $this->setError($requestKey, new ClientException('Connect timeout'));
            } else {
                throw new ClientException('Connect timeout');
            }
        }
    }
    
    private function checkinSocketByRequestKey($requestKey) {
        $socket = $this->requestKeySocketMap[$requestKey];
        $socketId = (int) $socket;
        
        unset(
            $this->socketIdRequestKeyMap[$socketId],
            $this->requestKeySocketMap[$requestKey]
        );
    }
    
    private function closeSocketByRequestKey($requestKey) {
        $socket = $this->requestKeySocketMap[$requestKey];
        $socketId = (int) $socket;
        
        @fclose($socket);
        
        unset(
            $this->socketIdRequestKeyMap[$socketId],
            $this->requestKeySocketMap[$requestKey],
            $this->socketPool[$socketId]
        );
    }
    
    private function finalizeResponse($requestKey) {
        if ($this->shouldKeepAlive($requestKey)) {
            $this->checkinSocketByRequestKey($requestKey);
        } else {
            $this->closeSocketByRequestKey($requestKey);
        }
        
        if ($this->isInMultiMode) {
            try {
                $canRedirect = $this->canRedirect($requestKey);
            } catch (ClientException $e) {
                $this->setError($requestKey, $e);
                return;
            }
        } else {
            $canRedirect = $this->canRedirect($requestKey);
        }
        
        if ($canRedirect) {
            $this->doRedirect($requestKey);
        } else {
            $this->states[$requestKey] = self::STATE_COMPLETE;
            $notifiableResponse = $this->buildClientResult($requestKey);
            $this->mediator->notify(
                self::EVENT_RESPONSE,
                $requestKey,
                $notifiableResponse,
                $this->requestStats[$requestKey]
            );
        }
        
        // clean up the mess
        $this->requestWriterKeyMap->detach($this->requestWriters[$requestKey]);
        $this->responseParserKeyMap->detach($this->responseParsers[$requestKey]);
        $this->requestWriters[$requestKey] = null;
        $this->responseParsers[$requestKey] = null;
    }
    
    private function shouldKeepAlive($requestKey) {
        /**
         * @var \Artax\Http\ValueResponse $response
         */
        $response = current($this->responses[$requestKey]);
        
        /**
         * @var \Artax\Http\Request $request
         */
        $request = $this->requests[$requestKey];
        
        if ($request->hasHeader('Connection')
            && !strcasecmp($request->getCombinedHeader('Connection'), 'close')
        ) {
            return false;
        }
        
        if ($response->hasHeader('Connection')
            && !strcasecmp($response->getCombinedHeader('Connection'), 'close')
        ) {
            return false;
        }
        
        if ($response->getProtocol() < 1.1
            && !$response->hasHeader('Connection')
        ) {
            return false;
        }
        
        if (!$this->attributes[self::ATTR_KEEP_CONNS_ALIVE]) {
            return false;
        }
        
        // Connections must be closed after headers are read when the entity body is "artificially"
        // ignored by the client or parsing errors will occur on future requests to the pooled
        // persistent connection.
        if ($this->attributes[self::ATTR_IGNORE_BODY]
            && $request->getMethod() != Request::HEAD
        ) {
            return false;
        }
        
        return true;
    }
    
    private function canRedirect($requestKey) {
        /**
         * @var Http\StdRequest $request
         */
        $request = $this->requests[$requestKey];
        
        /**
         * @var Http\ValueResponse $response
         */
        $response = end($this->responses[$requestKey]);
        
        
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        $requestUri = new Uri(key($this->responses[$requestKey]));
        $redirectLocation = $response->getCombinedHeader('Location');
        if (!$requestUri->canResolve($redirectLocation)) {
            return false;
        }
        
        $newResponse = new StdResponse;
        $newResponse->import($response);
        
        $redirectLocation = $requestUri->resolve($redirectLocation);
        $newResponse->setHeader('Location', $redirectLocation->__toString());
        $this->responses[$requestKey][key($this->responses[$requestKey])] = $newResponse->export();
        
        
        $followLocation = $this->attributes[self::ATTR_FOLLOW_LOCATION];
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
        if (!($requestMethod == Request::GET || $requestMethod == Request::HEAD)
            && !($canFollowUnsafe & $followLocation)
        ) {
            return false;
        }
        
        if (isset($this->responses[$requestKey][$redirectLocation->__toString()])) {
            throw new ClientException(
                "Infinite redirect loop detected; cannot redirect to $redirectLocation"
            );
        }
        
        return true;
    }
    
    private function doRedirect($requestKey) {
        /**
         * @var Http\StdRequest $request
         */
        $request = $this->requests[$requestKey];
        
        /**
         * @var Http\ValueResponse $response
         */
        $response = end($this->responses[$requestKey]);
        
        
        $newUri = new Uri($response->getCombinedHeader('Location'));
        $this->requestUris[$requestKey] = $newUri;
        
        $newRequest = new StdRequest;
        $newRequest->import($request);
        $newRequest->setUri($newUri->__toString());
        $newRequest->setHeader('Host', $newUri->getAuthority());
        
        if ($this->attributes[self::ATTR_AUTO_REFERER_ON_FOLLOW]) {
            $newRequest->setHeader('Referer', $request->getUri());
        }
        
        $this->requests[$requestKey] = $newRequest;
        $this->states[$requestKey] = self::STATE_NEEDS_SOCKET;
        
        $notifiableResponse = $this->buildClientResult($requestKey);
        $this->mediator->notify(
            self::EVENT_REDIRECT,
            $requestKey,
            $notifiableResponse,
            $this->requestStats[$requestKey]
        );
        
        // Reset the request stats for the next request in the redirect chain
        $this->initializeRequestStats($requestKey);
    }
    
    private function buildClientResult($requestKey) {
        // New responses are pushed onto the end of the response array as redirections occur. We
        // reverse the array so that the final response in the redirect chain is first.
        $responses = array_reverse($this->responses[$requestKey]);
        
        return new ClientResult($responses);
    }
    
    /**
     * Close all open socket streams (checked-out sockets are not closed)
     * 
     * @return int Returns the count of sockets closed by the operation
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
     * Close all open sockets to the specified host (checked-out sockets are not closed)
     * 
     * @param string $host
     * @return int Returns the count of sockets closed by the operation
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
     * Close sockets according to how long they've been idle (checked-out sockets are not closed)
     * 
     * @param int $maxInactivitySeconds
     * @return int Returns the count of sockets closed by the operation
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
     * Assign optional Client attributes
     * 
     * @param int $attribute
     * @param mixed $value
     * @throws \Spl\KeyException On invalid attribute
     * @return void
     */
    public function setAttribute($attribute, $value) {
        if (!array_key_exists($attribute, $this->attributes)) {
            throw new KeyException(
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
     * Assign multiple Client attributes
     * 
     * @param mixed $arrayOrTraversable A key-value traversable list of attributes and their values
     * @return void
     */
    public function setAllAttributes($arrayOrTraversable) {
        foreach ($arrayOrTraversable as $attribute => $value) {
            $this->setAttribute($attribute, $value);
        }
    }
    
    private function setAttr100ContinueDelay($seconds) {
        $seconds = filter_var($seconds, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 3,
                'min_range' => 0
            )
        ));
        $this->attributes[self::ATTR_100_CONTINUE_DELAY] = $seconds;
    }
    
    private function setAttrKeepConnsAlive($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_KEEP_CONNS_ALIVE] = $boolFlag;
    }
    
    private function setAttrConnectTimeout($seconds) {
        $seconds = filter_var($seconds, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 5,
                'min_range' => 1
            )
        ));
        $this->attributes[self::ATTR_CONNECT_TIMEOUT] = $seconds;
    }
    
    private function setAttrTransferTimeout($seconds) {
        $seconds = filter_var($seconds, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 0,
                'min_range' => 0
            )
        ));
        $this->attributes[self::ATTR_TRANSFER_TIMEOUT] = $seconds;
    }
    
    private function setAttrIdleTransferTimeout($seconds) {
        $seconds = filter_var($seconds, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 0,
                'min_range' => 0
            )
        ));
        $this->attributes[self::ATTR_IDLE_TRANSFER_TIMEOUT] = $seconds;
    }
    
    private function setAttrFollowLocation($bitmask) {
        $this->attributes[self::ATTR_FOLLOW_LOCATION] = (int) $bitmask;
    }
    
    private function setAttrAutoRefererOnFollow($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_AUTO_REFERER_ON_FOLLOW] = $boolFlag;
    }
    
    private function setAttrConcurrencyLimit($maxSockets) {
        $maxSockets = filter_var($maxSockets, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 25,
                'min_range' => 1
            )
        ));
        
        $this->attributes[self::ATTR_CONCURRENCY_LIMIT] = $maxSockets;
    }
    
    private function setAttrHostConcurrencyLimit($maxHostSockets) {
        $maxHostSockets = filter_var($maxHostSockets, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 3,
                'min_range' => 1
            )
        ));
        
        $this->attributes[self::ATTR_HOST_CONCURRENCY_LIMIT] = $maxHostSockets;
    }
    
    private function setAttrClosePolicy($closePolicy) {
        if (!($closePolicy == self::CLOSE_POLICY_LEAST_RECENTLY_USED
            || $closePolicy == self::CLOSE_POLICY_OLDEST
            || $closePolicy == self::CLOSE_POLICY_LEAST_TRAFFIC
            || $closePolicy == self::CLOSE_POLICY_SLOWEST_AVG_DL_SPEED
        )) {
            throw new DomainException(
                'Invalid close policy'
            );
        }
        
        $this->attributes[self::ATTR_CLOSE_POLICY] = $closePolicy;
    }
    
    private function setAttrIoBufferSize($bytes) {
        $bytes = filter_var($bytes, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 8192,
                'min_range' => 1
            )
        ));
        
        $this->attributes[self::ATTR_IO_BUFFER_SIZE] = $bytes;
    }
    
    private function setAttrBufferBody($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_BUFFER_BODY] = $boolFlag;
    }
    
    private function setAttrVerbose($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_VERBOSE] = $boolFlag;
    }
    
    private function setAttrSslVerifyPeer($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_SSL_VERIFY_PEER] = $boolFlag;
    }
    
    private function setAttrSslAllowSelfSigned($boolFlag) {
        $boolFlag = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
        $this->attributes[self::ATTR_SSL_ALLOW_SELF_SIGNED] = $boolFlag;
    }
    
    private function setAttrSslVerifyDepth($depth) {
        $depth = filter_var($depth, FILTER_VALIDATE_INT, array(
            'options' => array(
                'default' => 5,
                'min_range' => 1
            )
        ));
        $this->attributes[self::ATTR_SSL_VERIFY_DEPTH] = $depth;
    }
    
    /**
     * Retrieve diagnostic statistics from a request in the previous batch
     * 
     * If the previous request was made using the `Client::send` method, no $requestKey argument
     * is necessary (default = 0).
     * 
     * Stat array keys include:
     * 
     *  - connectedAt: The micro-timestamp when a socket connection was procured for the request
     *  - lastSentAt: The micro-timestamp of the final socket IO write
     *  - lastRecdAt: The micro-timestamp of the final socket IO read
     *  - bytesSent: The number of bytes sent to make the request
     *  - bytesRecd: The number of bytes read from the raw response message
     *  - avgUpKbps: The average upload speed in KB/s (kilobytes per second)
     *  - avgDownKbps: The average download speed in KB/s (kilobytes per second)
     * 
     * Note that request stats are reset on each redirected response so these results will only
     * reflect activity during the final request in a redirected response chain.
     * 
     * @param string $requestKey Which request's stats do we want?
     * @throws \Spl\KeyException On invalid request key
     * @return array
     */
    public function getRequestStats($requestKey = 0) {
        if (!isset($this->requestStats[$requestKey])) {
            throw new KeyException(
                'Invalid request key'
            );
        } else {
            return $this->requestStats[$requestKey];
        }
    }
    
    /**
     * Write event listener
     * 
     * WARNING: This public method ONLY exists to preserve compatibility with PHP 5.3. As of
     * PHP 5.4, the ability to bind closures to an object's scope allows us to hide the same
     * functionality behind the Client black-box. Userland code SHOULD NOT rely on this function's
     * availability as it will eventually be removed in future versions once support for PHP 5.3 is 
     * no longer required.
     * 
     * @param Http\RequestWriter $writer
     * @param string $data
     * @param int $bytes
     * @return void
     */
    public function onWrite(RequestWriter $writer, $data, $bytes) {
        if (!$this->requestWriterKeyMap->contains($writer)) {
            return;
        }
        
        if ($this->attributes[self::ATTR_VERBOSE]) {
            echo $data;
        }
        
        $requestKey = $this->requestWriterKeyMap->offsetGet($writer);
        $socket = $this->requestKeySocketMap[$requestKey];
        $socketId = (int) $socket;
        $now = microtime(true);
        
        $this->socketPool[$socketId]['activityAt'] = $now;
        $this->socketPool[$socketId]['bytesSent'] += $bytes;
        $this->requestStats[$requestKey]['lastSentAt'] = $now;
        $this->requestStats[$requestKey]['bytesSent'] += $bytes;
        
        if ($this->requestStats[$requestKey]['firstSentAt']) {
            $elapsedTime = ($now - $this->requestStats[$requestKey]['connectedAt']);
            $kbps = round((($this->requestStats[$requestKey]['bytesSent'] / $elapsedTime) / 1024), 2);
            $this->requestStats[$requestKey]['avgUpKbps'] = $kbps;
        } else {
            $this->requestStats[$requestKey]['firstSentAt'] = $now;
        }
        
        $this->mediator->notify(
            self::EVENT_WRITE,
            $requestKey,
            $data,
            $bytes,
            $this->requestStats[$requestKey]
        );
    }
    
    /**
     * Read event listener
     * 
     * WARNING: This public method ONLY exists to preserve compatibility with PHP 5.3. As of
     * PHP 5.4, the ability to bind closures to an object's scope allows us to hide the same
     * functionality behind the Client black-box. Userland code SHOULD NOT rely on this function's
     * availability as it will eventually be removed in future versions once support for PHP 5.3 is 
     * no longer required.
     * 
     * @param Http\Parsing\ResponseParser $parser
     * @param string $data
     * @param int $bytes
     * @param int $bodySize Will be NULL if the entity body size is unknown
     * @return void
     */
    public function onRead(ResponseParser $parser, $data, $bytes, $bodySize) {
        if (!$this->responseParserKeyMap->contains($parser)) {
            return;
        }
        
        if ($this->attributes[self::ATTR_VERBOSE]) {
            echo $data;
        }
        
        $requestKey = $this->responseParserKeyMap->offsetGet($parser);
        $socket = $this->requestKeySocketMap[$requestKey];
        $socketId = (int) $socket;
        $now = microtime(true);
        
        $downloadingTimeIncrement = $now - $this->socketPool[$socketId]['activityAt'];
        
        $this->socketPool[$socketId]['totalDownloadingTime'] += $downloadingTimeIncrement;
        $this->socketPool[$socketId]['activityAt'] = $now;
        $this->socketPool[$socketId]['bytesRecd'] += $bytes;
        $this->requestStats[$requestKey]['lastRecdAt'] = $now;
        $this->requestStats[$requestKey]['bytesRecd'] += $bytes;
        
        if ($this->requestStats[$requestKey]['firstRecdAt']) {
            $elapsedTime = ($now - $this->requestStats[$requestKey]['firstRecdAt']);
            $kbps = round((($this->requestStats[$requestKey]['bytesRecd'] / $elapsedTime) / 1024), 2);
            $this->requestStats[$requestKey]['avgDownKbps'] = $kbps;
        } else {
            $this->requestStats[$requestKey]['firstRecdAt'] = $now;
        }
        
        $this->mediator->notify(
            self::EVENT_READ,
            $requestKey,
            $data,
            $bytes,
            $bodySize,
            $this->requestStats[$requestKey]
        );
    }
    
}