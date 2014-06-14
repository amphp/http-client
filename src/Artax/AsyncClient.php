<?php

namespace Artax;

use Alert\Reactor;

class AsyncClient implements NonBlockingClient {
    
    use ObservableSubject;
    
    private $reactor;
    private $sockets;
    private $requests;
    private $requestQueue;
    private $socketFactory;
    
    private $hasExtZlib;
    private $autoEncoding = TRUE;
    private $useKeepAlive = TRUE;
    private $connectTimeout = 15;
    private $transferTimeout = 30;
    private $keepAliveTimeout = 30;
    private $followLocation = TRUE;
    private $autoReferer = TRUE;
    private $maxConnections = -1;
    private $maxConnectionsPerHost = 8;
    private $maxHeaderBytes = -1;
    private $maxBodyBytes = -1;
    private $bodySwapSize = 2097152;
    private $storeBody = TRUE;
    private $bufferBody = TRUE;
    private $bindToIp;
    private $continueDelay = 3;
    private $expectContinue = TRUE;
    private $ioGranularity = 65536;
    private $verboseRead = FALSE;
    private $verboseSend = FALSE;
    private $userAgentString = '';
    private $tlsOptions = [
        'verify_peer' => TRUE,
        'allow_self_signed' => NULL,
        'cafile' => NULL,
        'capath' => NULL,
        'local_cert' => NULL,
        'passphrase' => NULL,
        'CN_match' => NULL,
        'verify_depth' => NULL,
        'ciphers' => NULL,
        'SNI_enabled' => NULL,
        'SNI_server_name' => NULL
    ];
    
    function __construct(Reactor $reactor, SocketFactory $sf = NULL) {
        $this->reactor = $reactor;
        $this->socketFactory = $sf ?: new SocketFactory;
        $this->sockets = new \SplObjectStorage;
        $this->requests = new \SplObjectStorage;
        $this->requestQueue = new \SplObjectStorage;
        $this->tlsOptions['cafile'] = dirname(dirname(__DIR__)) . '/certs/cacert.pem';
        $this->hasExtZlib = extension_loaded('zlib');
    }
    
    /**
     * Asynchronously request an HTTP resource
     * 
     * @param mixed $uriOrRequest An HTTP URI string or Artax\Request instance
     * @param callable $onResponse A callback to receive the Artax\Response object on completion
     * @param callable $onError A callback to receive an exception object on retrieval failure
     * @return void
     */
    function request($uriOrRequest, callable $onResponse, callable $onError) {
        $request = $this->normalizeRequest($uriOrRequest);
        $rs = new RequestState;
        
        $rs->request = $request;
        $rs->authority = $this->generateAuthorityFromUri($request->getUri());
        
        $rs->onResponse = $onResponse;
        $rs->onError = $onError;
        
        $this->requestQueue->attach($request, $rs);
        
        $this->notifyObservations(self::REQUEST, [$request, NULL]);
        
        if ($this->resolveDns($rs)) {
            $this->assignRequestSockets();
        }
    }
    
    /**
     * Cancel a specific outstanding request
     * 
     * @param \Artax\Request $request
     * @return void
     */
    function cancel(Request $request) {
        if ($this->requestQueue->contains($request)) {
            $this->requestQueue->detach($request);
            $this->notifyObservations(self::CANCEL, [$request, NULL]);
        } elseif ($this->requests->contains($request)) {
            $rs = $this->requests->offsetGet($request);
            $this->checkinSocket($rs);
            $this->clearSocket($rs);
            $this->endRequestSubscriptions($rs);
            $this->requests->detach($request);
            $this->notifyObservations(self::CANCEL, [$request, NULL]);
        }
    }
    
    /**
     * Cancel all outstanding requests
     * 
     * @return void
     */
    function cancelAll() {
        foreach ($this->requests as $request) {
            $this->cancel($request);
        }
        foreach ($this->requestQueue as $request) {
            $this->cancel($request);
        }
    }
    
    /**
     * Assign multiple client options from a key-value array
     * 
     * @param array $options An array matching option name keys to option values
     * @throws \DomainException On unknown option key
     * @return void
     */
    function setAllOptions(array $options) {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }
    
    /**
     * Assign a client option
     * 
     * @param string $key Option key
     * @param mixed $value Option value
     * @throws \DomainException On unknown option key
     * @return void
     */
    function setOption($key, $value) {
        switch (strtolower($key)) {
            case 'usekeepalive':
                $this->setUseKeepAlive($value);
                break;
            case 'connecttimeout':
                $this->setConnectTimeout($value);
                break;
            case 'transfertimeout':
                $this->setTransferTimeout($value);
                break;
            case 'keepalivetimeout':
                $this->setKeepAliveTimeout($value);
                break;
            case 'followlocation':
                $this->setFollowLocation($value);
                break;
            case 'autoreferer':
                $this->setAutoReferer($value);
                break;
            case 'maxconnections':
                $this->setMaxConnections($value);
                break;
            case 'maxconnectionsperhost':
                $this->setMaxConnectionsPerHost($value);
                break;
            case 'continuedelay':
                $this->setContinueDelay($value);
                break;
            case 'bufferbody':
                $this->setBufferBody($value);
                break;
            case 'maxheaderbytes':
                $this->setMaxHeaderBytes($value);
                break;
            case 'maxbodybytes':
                $this->setMaxBodyBytes($value);
                break;
            case 'bodyswapsize':
                $this->setBodySwapSize($value);
                break;
            case 'storebody':
                $this->setStoreBody($value);
                break;
            case 'bindtoip':
                $this->setBindToIp($value);
                break;
            case 'expectcontinue':
                $this->setExpectContinue($value);
                break;
            case 'iogranularity':
                $this->setIoGranularity($value);
            case 'autoencoding':
                $this->setAutoEncoding($value);
                break;
            case 'verboseread':
                $this->setVerboseRead($value);
                break;
            case 'verbosesend':
                $this->setVerboseSend($value);
                break;
            case 'tlsoptions':
                $this->setTlsOptions($value);
                break;
            case 'useragentstring':
                $this->setUserAgentString($value);
                break;
            default:
                throw new \DomainException(
                    "Unknown option key: {$key}"
                );
        }
    }
    
    private function setUserAgentString($value) {
        if ($value && is_string($value)) {
            $this->userAgentString = $value;
        } else {
            throw new \InvalidArgumentException(
                'User Agent requires a non-empty string'
            );
        }
    }
    
    private function setUseKeepAlive($bool) {
        $this->useKeepAlive = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setConnectTimeout($seconds) {
        $this->connectTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 5,
            'min_range' => -1
        )));
    }
    
    private function setTransferTimeout($seconds) {
        $this->transferTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 30,
            'min_range' => -1
        )));
    }
    
    private function setKeepAliveTimeout($seconds) {
        $this->keepAliveTimeout = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 30,
            'min_range' => 0
        )));
    }
    
    private function setFollowLocation($bool) {
        $this->followLocation = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setAutoReferer($bool) {
        $this->autoReferer = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setMaxConnections($int) {
        $this->maxConnections = filter_var($int, FILTER_VALIDATE_INT, array('options' => array(
            'default' => -1,
            'min_range' => -1
        )));
    }
    
    private function setMaxConnectionsPerHost($int) {
        $this->maxConnectionsPerHost = filter_var($int, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 8,
            'min_range' => -1
        )));
    }
    
    private function setContinueDelay($seconds) {
        $this->continueDelay = filter_var($seconds, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 3,
            'min_range' => 0
        )));
    }
    
    private function setBufferBody($bool) {
        $this->bufferBody = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setMaxHeaderBytes($bytes) {
        $this->maxHeaderBytes = (int) $bytes;
    }
    
    private function setMaxBodyBytes($bytes) {
        $this->maxBodyBytes = (int) $bytes;
    }
    
    private function setBodySwapSize($bytes) {
        $this->bodySwapSize = (int) $bytes;
    }
    
    private function setStoreBody($bool) {
        $this->storeBody = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setIoGranularity($bytes) {
        $this->ioGranularity = filter_var($bytes, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 65536,
            'min_range' => 1
        )));
    }
    
    private function setBindToIp($ip) {
        $this->bindToIp = filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    private function setExpectContinue($bool) {
        $this->expectContinue = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setAutoEncoding($bool) {
        $this->autoEncoding = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setVerboseRead($bool) {
        $this->verboseRead = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setVerboseSend($bool) {
        $this->verboseSend = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setTlsOptions(array $opt) {
        $opt = array_filter(array_intersect_key($opt, $this->tlsOptions), function($k) { return !is_null($k); });
        $this->tlsOptions = array_merge($this->tlsOptions, $opt);
    }
    
    /**
     * @TODO Refactor to allow for non-blocking DNS lookups. Currently this is the only part of the
     * process that has the potential to block because it relies on PHP for DNS lookups.
     */
    private function resolveDns(RequestState $rs) {
        $urlParts = parse_url($rs->authority);

        if (filter_var($urlParts['host'], FILTER_VALIDATE_IP)) {
            $dnsLookupSucceeded = TRUE;
        } elseif (($ip = gethostbyname($urlParts['host'])) && ($ip === $urlParts['host'])) {
            $this->requests->attach($rs->request, $rs);
            $this->onError($rs, new DnsException(
                'DNS resolution failed for ' . $urlParts['host']
            ));
            $dnsLookupSucceeded = FALSE;
        } else {
            $rs->authority = $ip . ':' . $urlParts['port'];
            $dnsLookupSucceeded = TRUE;
        }
        
        return $dnsLookupSucceeded;
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

    private function assignRequestSockets() {
        foreach ($this->requestQueue as $request) {
            $rs = $this->requestQueue->offsetGet($request);
            if ($socket = $this->checkoutExistingSocket($rs)) {
                $this->finalizeRequestSocket($rs, $request, $socket, $isNewSocket = FALSE);
            } elseif ($socket = $this->checkoutNewSocket($rs)) {
                $this->finalizeRequestSocket($rs, $request, $socket, $isNewSocket = TRUE);
            }
        }
    }

    private function finalizeRequestSocket(RequestState $rs, Request $request, $socket, $isNewSocket) {
        $rs->socket = $socket;
        if ($isNewSocket) {
            $this->assignSocketOptions($request, $rs->socket);
        }
        $this->applyRequestSocketObservations($rs);
        $this->requestQueue->detach($request);
        $this->requests->attach($request, $rs);
    }

    private function checkoutExistingSocket(RequestState $rs) {
        foreach ($this->sockets as $socket) {
            $isInUse = (bool) $this->sockets->offsetGet($socket);
            $isAuthorityMatch = ($rs->authority === $socket->getAuthority());
            
            if (!$isInUse && $isAuthorityMatch) {
                $this->sockets->attach($socket, $rs);
                return $socket;
            }
        }
    }
    
    private function checkoutNewSocket(RequestState $rs) {
        if ($this->isNewConnectionAllowed($rs->authority)) {
            $socket = $this->socketFactory->make($this->reactor, $rs->authority);
            $this->sockets->attach($socket, $rs);
            
            return $socket;
        }
    }
    
    private function isNewConnectionAllowed($authority) {
        if ($this->maxConnections < 0 || ($this->sockets->count() < $this->maxConnections)) {
            $hostCount = 0;
            
            foreach ($this->sockets as $socket) {
                $hostCount += ($socket->getAuthority() === $authority);
            }
            
            $result = ($hostCount < $this->maxConnectionsPerHost);
            
        } else {
            $result = FALSE;
        }
        
        return $result;
    }
    
    private function assignSocketOptions(Request $request, Socket $socket) {
        $opts = [
            'connectTimeout' => $this->connectTimeout,
            'bindToIp' => $this->bindToIp,
        ];
        if (parse_url($request->getUri(), PHP_URL_SCHEME) === 'https'){
            $opts['tlsOptions'] = $this->tlsOptions;
        }
        
        $socket->setAllOptions($opts);
    }
    
    private function checkinSocket(RequestState $rs) {
        if ($socket = $rs->socket) {
            $isInUse = FALSE;
            $this->sockets->attach($socket, $isInUse);
        }
    }
    
    private function clearSocket(RequestState $rs) {
        if ($socket = $rs->socket) {
            $socket->stop();
            $socket->removeAllObservations();
            $this->sockets->detach($socket);
        }
    }
    
    private function applyRequestSocketObservations(RequestState $rs) {
        $rs->parser = new MessageParser(MessageParser::MODE_RESPONSE);
        $rs->parser->setOptions([
            'maxHeaderBytes' => $this->maxHeaderBytes,
            'maxBodyBytes' => $this->maxBodyBytes,
            'bodySwapSize' => $this->bodySwapSize,
            'storeBody' => $this->storeBody,
            'beforeBody' => function($parsedResponseArr) use ($rs) {
                $this->notifyObservations(self::HEADERS, [$rs->request, $parsedResponseArr]);
            },
            'onBodyData' => function($data) use ($rs) {
                $this->notifyObservations(self::BODY_DATA, [$rs->request, $data]);
            }
        ]);
        
        $onSockReady = function() use ($rs) { $this->onSockReady($rs); };
        $onSockWrite = function($data) use ($rs) { $this->onSockWrite($rs, $data); };
        $onSockRead  = function($data) use ($rs) { $this->onSockRead($rs, $data); };
        $onSockError = function($e) use ($rs) { $this->onError($rs, $e); };
        
        $rs->socketObservation = $rs->socket->addObservation([
            Socket::READY => $onSockReady,
            Socket::SEND => $onSockWrite,
            Socket::DATA => $onSockRead,
            Socket::ERROR => $onSockError,
        ]);
        
        $rs->socket->start();
    }
    
    private function onSockReady(RequestState $rs) {
        $this->notifyObservations(self::SOCKET, [$rs->request, NULL]);
        $this->initializeRequestHeaderWrite($rs);
    }
    
    private function onSockWrite(RequestState $rs, $dataSent) {
        if ($this->verboseSend) {
            echo $dataSent;
        }
        
        $this->notifyObservations(self::SOCK_DATA_OUT, [$rs->request, $dataSent]);
    }
    
    private function onSockRead(RequestState $rs, $data) {
        if ($this->verboseRead) {
            echo $data;
        }
        
        $this->notifyObservations(self::SOCK_DATA_IN, [$rs->request, $data]);
        $this->parseSockData($rs, $data);
    }
    
    private function parseSockData(RequestState $rs, $data) {
        try {
            while ($parsedResponseArr = $rs->parser->parse($data)) {
                $rs->response = $this->buildResponseFromParsedArray($rs->request, $parsedResponseArr);
                if ($parsedResponseArr['status'] != 100) {
                    $this->onResponse($rs);
                }
                $data = '';
            }
        } catch (MessageParseException $e) {
            $this->onError($rs, $e);
        }
    }
    
    private function initializeRequestHeaderWrite(RequestState $rs) {
        if ($this->transferTimeout > 0) {
            $rs->transferTimeoutWatcher = $this->reactor->once(function() use ($rs) {
                $this->onError($rs, new TimeoutException);
            }, $this->transferTimeout * 1000);
        }
        
        $request = $rs->request;
        $socket = $rs->socket;
        
        $rs->parser->enqueueResponseMethodMatch($request->getMethod());
        $rawHeaders = $this->generateRawRequestHeaders($request);
        
        if ($request->hasBody()) {
            $rs->bodyDrainObservation = $socket->addObservation([
                Socket::DRAIN => function() use ($rs) { $this->afterRequestHeaderWrite($rs); }
            ]);
        }
        
        $socket->send($rawHeaders);
    }
    
    private function afterRequestHeaderWrite(RequestState $rs) {
        if ($this->requestExpects100Continue($rs->request)) {
            $rs->continueDelayWatcher = $this->reactor->once(function() use ($rs) {
                $this->initializeRequestBodyWrite($rs);
            }, $this->continueDelay * 1000);
        } else {
            $this->initializeRequestBodyWrite($rs);
        }
    }
    
    private function requestExpects100Continue(Request $request) {
        if (!$request->hasHeader('Expect')) {
            $expectsContinue = FALSE;
        } elseif (stripos(implode(',', $request->getHeader('Expect')), '100-continue') !== FALSE) {
            $expectsContinue = TRUE;
        } else {
            $expectsContinue = FALSE;
        }
        
        return $expectsContinue;
    }
    
    private function initializeRequestBodyWrite(RequestState $rs) {
        $body = $rs->request->getBody();
        $socket = $rs->socket;
        
        if (is_string($body)) {
            // IMPORTANT: cancel the DRAIN observation BEFORE sending the body or we'll be stuck in
            // an infinite send/drain loop.
            $rs->bodyDrainObservation->cancel();
            $socket->send($body);
        } elseif ($body instanceof \Iterator) {
            $rs->bodyDrainObservation->modify([
                Socket::DRAIN => function() use ($rs) { $this->streamIteratorRequestEntityBody($rs); }
            ]);
            $this->streamIteratorRequestEntityBody($rs);
        }
    }
    
    private function streamIteratorRequestEntityBody(RequestState $rs) {
        $request = $rs->request;
        $body = $request->getBody();
        
        if ($body->valid()) {
            $chunk = $body->current();
            $body->next();
            $socket = $rs->socket;
            $socket->send($chunk);
        } else {
            $rs->bodyDrainObservation->cancel();
        }
    }
    
    /**
     * @TODO Add support for sending proxy-style absolute URIs in the raw request message
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
    
    private function onError(RequestState $rs, \Exception $e) {
        $parser = $rs->parser;
        
        if ($e->getCode() === Socket::E_SOCKET_GONE
            && $parser->getState() == MessageParser::BODY_IDENTITY_EOF
        ) {
            $this->finalizeBodyEofResponse($rs);
        } else {
            $this->doError($rs, $e);
        }
        
        if ($this->requestQueue->count()) {
            $this->assignRequestSockets();
        }
    }
    
    private function finalizeBodyEofResponse(RequestState $rs) {
        $parser = $rs->parser;
        $parsedResponseArr = $parser->getParsedMessageArray();
        $response = $this->buildResponseFromParsedArray($rs->request, $parsedResponseArr);
        $rs->response = $response;
        $this->onResponse($rs);
    }
    
    private function doError(RequestState $rs, \Exception $e) {
        $this->endRequestSubscriptions($rs);
        
        $partialMsgArr = $rs->parser ? $rs->parser->getParsedMessageArray() : [];
        $this->notifyObservations(self::ERROR, [$rs->request, $partialMsgArr, $e]);
        
        // Only inform the error callback if event subscribers don't cancel the request
        if ($this->requests->contains($rs->request)) {
            $this->requests->detach($rs->request);
            $this->requestQueue->detach($rs->request);
            
            $onError = $rs->onError;
            $onError($e, $rs->request);
        }
    }
    
    private function endRequestSubscriptions(RequestState $rs) {
        $rs->socket = NULL;
        
        if ($rs->socketObservation) {
            $rs->socketObservation->cancel();
            $rs->socketObservation = NULL;
        }
        if ($rs->bodyDrainObservation) {
            $rs->bodyDrainObservation->cancel();
            $rs->bodyDrainObservation = NULL;
        }
        if ($rs->continueDelayWatcher) {
            $this->reactor->cancel($rs->continueDelayWatcher);
            $rs->continueDelayWatcher = NULL;
        }
        if ($rs->transferTimeoutWatcher) {
            $this->reactor->cancel($rs->transferTimeoutWatcher);
            $rs->transferTimeoutWatcher = NULL;
        }
    }
    
    private function onResponse(RequestState $rs) {
        $this->checkinSocket($rs);
        
        if ($this->shouldCloseSocket($rs)) {
            $this->clearSocket($rs);
        }
        
        $this->endRequestSubscriptions($rs);
        
        if ($this->hasExtZlib) {
            $this->decompressResponseBody($rs->response);
        }
        
        if ($newUri = $this->getRedirectUri($rs)) {
            $this->redirect($rs, $newUri);
        } else {
            $this->notifyObservations(self::RESPONSE, [$rs->request, $rs->response]);
            $onResponse = $rs->onResponse;
            $onResponse($rs->response, $rs->request);
            $this->requests->detach($rs->request);
            $this->requestQueue->detach($rs->request);
        }
        
        if ($this->requestQueue->count()) {
            $this->assignRequestSockets();
        }
    }
    
    private function decompressResponseBody(Response $response) {
        if (!$response->hasHeader('Content-Encoding')) {
            return;
        }
        
        $ceHeader = trim(current($response->getHeader('Content-Encoding')));
        
        if (!strcasecmp($ceHeader, 'gzip')) {
            $this->doGzipInflate($response);
        }
    }
    
    private function doGzipInflate(Response $response) {
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
    
    private function endContinueDelay(Request $request) {
        $rs = $this->requests->offsetGet($request);
        $this->reactor->cancel($rs->continueDelayWatcher);
        $rs->continueDelayWatcher = NULL;
        
        $this->initializeRequestBodyWrite($rs);
    }
    
    private function buildResponseFromParsedArray(Request $request, array $parsedResponseArr) {
        if ($parsedResponseArr['status'] == 100) {
            $this->endContinueDelay($request);
            return NULL;
        }
        
        if (($body = $parsedResponseArr['body']) && $this->bufferBody) {
            $body = stream_get_contents($body);
        }
        
        $response = new Response;
        $response->setStatus($parsedResponseArr['status']);
        $response->setReason($parsedResponseArr['reason']);
        $response->setProtocol($parsedResponseArr['protocol']);
        $response->setBody($body);
        $response->setAllHeaders($parsedResponseArr['headers']);
        
        return $response;
    }
    
    private function shouldCloseSocket(RequestState $rs) {
        $request = $rs->request;
        $response = $rs->response;
        
        $requestConnHeader = $request->hasHeader('Connection')
            ? current($request->getHeader('Connection'))
            : NULL;
        
        $responseConnHeader = $response->hasHeader('Connection')
            ? current($response->getHeader('Connection'))
            : NULL;
        
        if ($requestConnHeader && !strcasecmp($requestConnHeader, 'close')) {
            $result = TRUE;
        } elseif ($responseConnHeader && !strcasecmp($responseConnHeader, 'close')) {
            $result = TRUE;
        } elseif ($response->getProtocol() == '1.0' && !$responseConnHeader) {
            $result = TRUE;
        } elseif (!$this->useKeepAlive) {
            $result = TRUE;
        } else {
            $result = FALSE;
        }
        
        return $result;
    }
    
    private function getRedirectUri(RequestState $rs) {
        $request = $rs->request;
        $response = $rs->response;
        
        if (!($this->followLocation && $response->hasHeader('Location'))) {
            return NULL;
        }
        
        $status = $response->getStatus();
        $method = $request->getMethod();
        
        if ($status < 200 || $status > 399 || !($method === 'GET' || $method === 'HEAD')) {
            return NULL;
        }
        
        $requestUri = new Uri($request->getUri());
        $redirectLocation = current($response->getHeader('Location'));
        
        if (!$requestUri->canResolve($redirectLocation)) {
            return NULL;
        }
        
        $newUri = $requestUri->resolve($redirectLocation);
        
        $rs->redirectHistory[] = $request->getUri();
        
        return in_array($newUri->__toString(), $rs->redirectHistory) ? NULL : $newUri;
    }
    
    private function redirect(RequestState $rs, Uri $newUri) {
        $request = $rs->request;
        
        $refererUri = $request->getUri();
        $redirectResponse = $rs->response;
        
        $rs->authority = $this->generateAuthorityFromUri($newUri);
        $request->setUri($newUri->__toString());
        $request->setHeader('Host', parse_url($rs->authority, PHP_URL_HOST));
        
        if (($body = $request->getBody()) && is_resource($body)) {
            rewind($body);
        }
        
        if ($this->autoReferer) {
            $request->setHeader('Referer', $refererUri);
        }
        
        $this->requestQueue->attach($request, $rs);
        $this->notifyObservations(self::REDIRECT, [$request, $redirectResponse]);
    }
    
    private function normalizeRequest($request) {
        if (is_string($request)) {
            $uri = $this->buildUriFromString($request);
            $request = new Request;
        } elseif ($request instanceof Request) {
            $uri = $this->buildUriFromString((string) $request->getUri());
        } else {
            throw new \InvalidArgumentException(
                'Request must be a valid HTTP URI or Artax\Request instance'
            );
        }
        
        if ($uri) {
            $request->setUri($uri->__toString());
        } else {
            throw new \InvalidArgumentException(
                'Request must specify a valid HTTP URI'
            );
        }
        
        if (!$method = $request->getMethod()) {
            $method = 'GET';
            $request->setMethod($method);
        }
        
        if (!$request->hasHeader('User-Agent')) {
            $userAgentString = $this->userAgentString ?: ObservableClient::USER_AGENT;
            $request->setHeader('User-Agent', $userAgentString);
        }
        
        if (!$request->hasHeader('Host')) {
            $this->normalizeRequestHostHeader($request);
        }
        
        if (!$protocol = $request->getProtocol()) {
            $request->setProtocol('1.1');
        } elseif (!($protocol == '1.0' || $protocol == '1.1')) {
            throw new \InvalidArgumentException(
                'Invalid request protocol: ' . $protocol
            );
        }
        
        $body = $request->getBody();
        
        if ($body instanceof BodyAggregate) {
            $body = $this->normalizeBodyAggregateRequest($request);
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
        } elseif ($body !== NULL) {
            throw new \InvalidArgumentException(
                'Request entity body must be a scalar or Iterator'
            );
        } 
        
        if ($body && $this->expectContinue && !$request->hasHeader('Expect')) {
            $request->setHeader('Expect', '100-continue');
        }
        
        if ($method === 'TRACE' || $method === 'HEAD' || $method === 'OPTIONS') {
            $request->setBody(NULL);
        }
        
        if (!$this->useKeepAlive) {
            $request->setHeader('Connection', 'close');
        }
        
        if ($this->autoEncoding && $this->hasExtZlib) {
            $request->setHeader('Accept-Encoding', 'gzip, identity');
        } elseif ($this->autoEncoding) {
            $request->removeHeader('Accept-Encoding');
        }
        
        return $request;
    }
    
    private function normalizeRequestHostHeader(Request $request) {
        $authority = $this->generateAuthorityFromUri($request->getUri());
        
        /**
         * Though servers are supposed to be able to handle standard port names on the end of the
         * Host header some fail to do this correctly. As a result, we strip the port from the end
         * if it's a standard 80/443
         */
        if (stripos($authority, ':80') || stripos($authority, ':443')) {
            $authority = parse_url($authority, PHP_URL_HOST);
        }
        
        $request->setHeader('Host', $authority);
    }
    
    private function normalizeBodyAggregateRequest(Request $request) {
        $body = $request->getBody();
        $request->setHeader('Content-Type', $body->getContentType());
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
    
    private function bufferIteratorResource(\Iterator $body) {
        $tmp = fopen('php://temp', 'r+');
        foreach ($body as $part) {
            fwrite($tmp, $part);
        }
        rewind($tmp);
        
        return new ResourceBody($tmp);
    }
    
    private function buildUriFromString($str) {
        try {
            $uri = new Uri($str);
            $scheme = $uri->getScheme();
            return (($scheme === 'http' || $scheme === 'https') && $uri->getHost()) ? $uri : NULL;
        } catch (\DomainException $e) {
            return NULL;
        }
    }
    
}

