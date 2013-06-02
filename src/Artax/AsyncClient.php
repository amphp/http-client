<?php

namespace Artax;

use Amp\Reactor,
    Artax\Parsing\Parser,
    Artax\Parsing\ParseException,
    Artax\Parsing\ParserFactory;

class AsyncClient implements ObservableClient {
    
    use Subject;
    
    const USER_AGENT = 'Artax/2.0.0-devel (PHP5.4+)';
    
    private $reactor;
    private $sockets;
    private $requests;
    private $requestQueue;
    private $parserFactory;
    private $socketFactory;
    private $idleSocketSubscriptions;
    
    private $useKeepAlive = TRUE;
    private $connectTimeout = 15;
    private $transferTimeout = 30;
    private $keepAliveTimeout = 30;
    private $followLocation = TRUE;
    private $autoReferer = TRUE;
    private $maxConnections = -1;
    private $maxConnectionsPerHost = 4;
    private $continueDelay = 3;
    private $bufferBody = TRUE;
    private $bindToIp;
    private $ioGranularity = 65536;
    private $verboseRead = FALSE;
    private $verboseSend = FALSE;
    private $tlsOptions;
    
    function __construct(Reactor $reactor, ParserFactory $opf = NULL, SocketFactory $sf = NULL) {
        $this->reactor = $reactor;
        $this->parserFactory = $opf ?: new ParserFactory;
        $this->socketFactory = $sf ?: new SocketFactory;
        $this->sockets = new \SplObjectStorage;
        $this->requests = new \SplObjectStorage;
        $this->requestQueue = new \SplObjectStorage;
        $this->idleSocketSubscriptions = new \SplObjectStorage;
        
        $this->tlsOptions = [
            'verify_peer' => TRUE,
            'allow_self_signed' => NULL,
            'cafile' => dirname(dirname(__DIR__)) . '/certs/cacert.pem',
            'capath' => NULL,
            'local_cert' => NULL,
            'passphrase' => NULL,
            'CN_match' => NULL,
            'verify_depth' => NULL,
            'ciphers' => NULL,
            'SNI_enabled' => NULL,
            'SNI_server_name' => NULL
        ];
    }
    
    function request($request, callable $onResponse, callable $onError) {
        $request = $this->normalizeRequest($request);
        $rs = new RequestState;
        
        $rs->request = $request;
        $rs->authority = $this->generateAuthorityFromUri($request->getUri());
        $rs->onResponse = $onResponse;
        $rs->onError = $onError;
        
        $this->requests->attach($request, $rs);
        $this->requestQueue->attach($request);
        
        $this->notify(self::REQUEST, [$request, NULL]);
        
        // Notified listeners may have already fulfilled the request
        if ($rs->response) {
            $this->onResponse($rs);
        } else {
            $this->assignRequestSockets();
        }
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
            $rs = $this->requests->offsetGet($request);
            
            if ($rs->socket = $this->checkoutExistingSocket($rs)) {
                $this->doSubscribe($rs);
                $this->requestQueue->detach($request);
            } elseif ($rs->socket = $this->checkoutNewSocket($rs)) {
                $this->assignSocketOptions($request, $rs->socket);
                $this->doSubscribe($rs);
                $this->requestQueue->detach($request);
            }
        }
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
            
            $onSockGoneSub = $socket->subscribe([
                Socket::TIMEOUT => function() use ($socket) { $this->clearSocket($socket); }
            ]);
            $onSockGoneSub->disable();
            
            $this->idleSocketSubscriptions->attach($socket, $onSockGoneSub);
            
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
            'keepAliveTimeout' => $this->keepAliveTimeout,
            'bindToIp' => $this->bindToIp,
        ];
        
        $uri = $request->getUri();
        
        if (parse_url($uri, PHP_URL_SCHEME) === 'https'){
            $opts['tlsOptions'] = $this->tlsOptions;
        }
        
        $socket->setAllOptions($opts);
    }
    
    private function checkinSocket(RequestState $rs) {
        if ($socket = $rs->socket) {
            $isInUse = FALSE;
            $this->sockets->attach($socket, $isInUse);
            
            $onSockGoneSubscription = $this->idleSocketSubscriptions->offsetGet($socket);
            $onSockGoneSubscription->enable();
        }
    }
    
    private function clearSocket(RequestState $rs) {
        if ($socket = $rs->socket) {
            $socket->stop();
            $socket->unsubscribeAll();
            $this->sockets->detach($socket);
            $this->idleSocketSubscriptions->detach($socket);
        }
    }
    
    private function doSubscribe(RequestState $rs) {
        $rs->parser = $this->parserFactory->make();
        $rs->parser->setOptions([
            'returnHeadersBeforeBody' => TRUE    
        ]);
        
        $onSockReady = function() use ($rs) { $this->initializeHeaderWrite($rs); };
        $onSockSend = function($data) use ($rs) { $this->onSend($rs, $data); };
        $onSockData = function($data) use ($rs) { $this->onData($rs, $data); };
        $onSockError = function($e) use ($rs) { $this->onError($rs, $e); };
        $onSockTimeout = function() use ($rs) { $this->handleSockReadTimeout($rs->socket); };
        
        $rs->sockSub = $rs->socket->subscribe([
            Socket::READY => $onSockReady,
            Socket::SEND => $onSockSend,
            Socket::DATA => $onSockData,
            Socket::ERROR => $onSockError,
            Socket::TIMEOUT => $onSockTimeout
        ]);
        
        $rs->socket->start();
    }
    
    private function onSend(RequestState $rs, $dataSent) {
        if ($this->verboseSend) {
            echo $dataSent;
        }
        $this->notify(self::SEND, [$rs->request, $dataSent]);
    }
    
    private function onData(RequestState $rs, $data) {
        if ($data !== '') {
            $this->notifyOnData($rs->request, $data);
        }
        
        $this->parse($rs, $data);
    }
    
    private function notifyOnData(Request $request, $data) {
        if ($this->verboseRead) {
            echo $data;
        }
        $this->notify(self::DATA, [$request, $data]);
    }
    
    private function parse(RequestState $rs, $data) {
        try {
            if (($parsedResponseArr = $rs->parser->parse($data))
                && $parsedResponseArr['headersOnly']
            ) {
                $this->notify(self::HEADERS, [$rs->request, $parsedResponseArr]);
                $this->parse($rs, $data = '');
            } elseif ($parsedResponseArr
                && ($response = $this->buildResponseFromParsedArray($rs->request, $parsedResponseArr))
            ) {
                $rs->response = $response;
                $this->onResponse($rs);
            }
        } catch (ParseException $e) {
            $this->onError($rs, $e);
        }
    }
    
    private function handleSockReadTimeout(Socket $socket) {
        // We only clear the socket on a read timeout if it's NOT IN USE because the transfer
        // timeout is the primary timeout as long as the socket is alive and a transfer is active.
        if (!$isInUse = $this->sockets->offsetGet($socket)) {
            $this->clearSocket($socket);
        }
    }
    
    private function initializeHeaderWrite(RequestState $rs) {
        if ($this->transferTimeout >= 0) {
            $this->initializeTransferTimeout($rs);
        }
        
        $request = $rs->request;
        $socket = $rs->socket;
        
        $rawHeaders = $this->generateRawRequestHeaders($request);
        
        if ($request->hasBody()) {
            $rs->bodyDrainSubscription = $socket->subscribe([
                Socket::DRAIN => function() use ($rs) { $this->afterHeaderWrite($rs); }
            ]);
        }
        
        $socket->send($rawHeaders);
    }
    
    private function initializeTransferTimeout(RequestState $rs) {
        $rs->transferTimeoutSubscription = $this->reactor->once(function() use ($rs) {
            $callback = $rs->onError;
            $callback($rs->request, new TimeoutException);
        }, $delay = $this->transferTimeout);
    }
    
    private function afterHeaderWrite(RequestState $rs) {
        $request = $rs->request;
        
        return $this->expects100Continue($request)
            ? $this->initializeContinueDelaySubscription($rs)
            : $this->initializeBodyWrite($rs);
    }
    
    private function initializeContinueDelaySubscription(RequestState $rs) {
        $rs->continueDelaySubscription = $this->reactor->once(function() use ($rs) {
            $rs->continueDelaySubscription->cancel();
            $this->initializeBodyWrite($rs->request, $rs->socket);
        }, $delay = $this->continueDelay);
    }
    
    private function expects100Continue(Request $request) {
        if (!$request->hasHeader('Expect')) {
            $expectsContinue = FALSE;
        } elseif (strcasecmp(current($request->getHeader('Expect')), '100-continue')) {
            $expectsContinue = FALSE;
        } else {
            $expectsContinue = TRUE;
        }
        
        return $expectsContinue;
    }
    
    private function initializeBodyWrite(RequestState $rs) {
        $request = $rs->request;
        $body = $request->getBody();
        $socket = $rs->socket;
        
        if (is_string($body)) {
            $rs->bodyDrainSubscription->cancel();
            // IMPORTANT: we have to cancel the DRAIN subscription BEFORE sending the body or we'll
            // be stuck in an infinite loop repeatedly sending the entity body.
            $socket->send($body);
        } elseif (is_resource($body)) {
            $rs->bodyDrainSubscription->modify([
                Socket::DRAIN => function() use ($rs) { $this->streamRequestBody($rs); }
            ]);
            $this->streamRequestBody($rs);
        } else {
            throw new \UnexpectedValueException;
        }
    }
    
    private function streamRequestBody(RequestState $rs) {
        $request = $rs->request;
        $body = $request->getBody();
        
        if (feof($body)) {
            $bodyDrainSubscription = $rs->bodyDrainSubscription;
            $bodyDrainSubscription->cancel();
        } else {
            $chunk = fread($body, $this->ioGranularity);
            $socket = $rs->socket;
            $socket->send($chunk);
        }
    }
    
    private function generateRawRequestHeaders(Request $request) {
        // @TODO Add support for proxy-style absolute URIs
        
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
            && $parser->getStatus() == Parser::BODY_IDENTITY_EOF
        ) {
            $this->finalizeBodyEofResponse($rs);
        } else {
            $this->endRequestSubscriptions($rs);
            $this->requests->detach($rs->request);
            $this->requestQueue->detach($rs->request);
            
            $onError = $rs->onError;
            $onError($e);
        }
        
        if ($this->requestQueue->count()) {
            $this->assignRequestSockets();
        }
    }
    
    private function finalizeBodyEofResponse(RequestState $rs) {
        $parser = $rs->parser;
        $parsedResponseArr = $parser->getParsedMessageArray();
        $response = $this->buildResponseFromParsedArray($rs, $parsedResponseArr);
        $rs->response = $response;
        $this->onResponse($rs);
    }
    
    private function endRequestSubscriptions(RequestState $rs) {
        $rs->socket = NULL;
        
        if ($rs->sockSub) {
            $rs->sockSub->cancel();
            $rs->sockSub = NULL;
        }
        if ($rs->bodyDrainSubscription) {
            $rs->bodyDrainSubscription->cancel();
            $rs->bodyDrainSubscription = NULL;
        }
        if ($rs->transferTimeoutSubscription) {
            $rs->transferTimeoutSubscription->cancel();
            $rs->transferTimeoutSubscription = NULL;
        }
    }
    
    private function onResponse(RequestState $rs) {
        $this->endRequestSubscriptions($rs);
        $this->checkinSocket($rs);
        
        if ($this->shouldCloseSocket($rs)) {
            $this->clearSocket($rs);
        }
        
        if ($newUri = $this->getRedirectUri($rs)) {
            $this->redirect($rs, $newUri);
        } else {
            $this->notify(self::RESPONSE, [$rs->request, $rs->response]);
            $onResponse = $rs->onResponse;
            $onResponse($rs->response);
            $this->requests->detach($rs->request);
            $this->requestQueue->detach($rs->request);
        }
        
        if ($this->requestQueue->count()) {
            $this->assignRequestSockets();
        }
    }
    
    private function endContinueDelay(Request $request) {
        $rs = $this->requests->offsetGet($request);
        $this->initializeBodyWrite($rs);
    }
    
    private function buildResponseFromParsedArray(Request $request, array $parsedResponseArr) {
        if ($parsedResponseArr['status'] == 100) {
            $this->endContinueDelay($request);
            return NULL;
        }
        
        $response = new Response;
        
        if (($body = $parsedResponseArr['body']) && $this->bufferBody) {
            $body = stream_get_contents($body);
        }
        
        $response->setStatus($parsedResponseArr['status']);
        $response->setReason($parsedResponseArr['reason']);
        $response->setProtocol($parsedResponseArr['protocol']);
        $response->setBody($body);
        
        foreach ($parsedResponseArr['headers'] as $field => $valueArr) {
            $response->setHeader($field, $valueArr);
        }
        
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
        
        $rs->authority = $this->generateAuthorityFromUri($newUri);
        $request->setUri($newUri->__toString());
        $request->setHeader('Host', $rs->authority);
        
        if (($body = $request->getBody()) && is_resource($body)) {
            rewind($body);
        }
        
        if ($this->autoReferer) {
            $request->setHeader('Referer', $request->getUri());
        }
        
        $this->requestQueue->attach($request);
        $this->notify(self::REDIRECT, [$request, NULL]);
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
        
        $authority = $this->generateAuthorityFromUri($uri);
        $request->setHeader('Host', $authority);
        
        if (!$request->hasHeader('User-Agent')) {
            $request->setHeader('User-Agent', self::USER_AGENT);
        }
        
        if (!$protocol = $request->getProtocol()) {
            $request->setProtocol('1.1');
        } elseif (!($protocol == '1.0' || $protocol == '1.1')) {
            throw new \InvalidArgumentException(
                'Invalid request protocol: ' . $protocol
            );
        }
        
        $body = $request->getBody();
        $bodyIsResource = is_resource($body);
        
        if (!($body || $body === '0')) {
            $request->removeHeader('Content-Length');
            $request->removeHeader('Transfer-Encoding');
        } elseif (is_resource($body)) {
            fseek($body, 0, SEEK_END);
            $contentLength = ftell($body);
            rewind($body);
            $request->setHeader('Content-Length', $contentLength);
            $request->removeHeader('Transfer-Encoding');
        } elseif ($body && (is_string($body) || is_object($body) && method_exists($body, '__toString'))) {
            $body = (string) $body;
            $request->setHeader('Content-Length', strlen($body));
            $request->removeHeader('Transfer-Encoding');
        } elseif ($body) {
            throw new \InvalidArgumentException(
                'Request entity body must be a string, seekable resource or object implementing __toString()'
            );
        }
        
        if ($method === 'TRACE' || $method === 'HEAD' || $method === 'OPTIONS') {
            $request->setBody(NULL);
        }
        
        if (!$this->useKeepAlive) {
            $request->setHeader('Connection', 'close');
        }
        
        return $request;
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
    
    function setResponse(Request $request, Response $response) {
        if ($this->requests->contains($request)) {
            $rs = $this->requests->offsetGet($request);
            $rs->response = $response;
        }
    }
    
    function setAllOptions(array $options) {
        foreach ($options as $key => $value) {
            $this->setOption($key, $value);
        }
    }
    
    function setOption($key, $value) {
        $validKeys = array(
            'useKeepAlive',
            'connectTimeout',
            'transferTimeout',
            'keepAliveTimeout',
            'followLocation',
            'autoReferer',
            'maxConnections',
            'maxConnectionsPerHost',
            'continueDelay',
            'bufferBody',
            'bindToIp',
            'ioGranularity',
            'verboseRead',
            'verboseSend',
            'tlsOptions'
        );
        
        if (in_array($key, $validKeys)) {
            $setter = 'set' . ucfirst($key);
            $this->$setter($value);
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
            'default' => 4,
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
    
    private function setIoGranularity($bytes) {
        $this->ioGranularity = filter_var($bytes, FILTER_VALIDATE_INT, array('options' => array(
            'default' => 65536,
            'min_range' => 1
        )));
    }
    
    private function setBindToIp($ip) {
        $this->bindToIp = filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    private function setVerboseRead($bool) {
        $this->verboseRead = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setVerboseSend($bool) {
        $this->verboseSend = filter_var($bool, FILTER_VALIDATE_BOOLEAN);
    }
    
    private function setTlsOptions(array $opt) {
        $opt = array_filter(array_intersect_key($this->tlsOptions, $opt), function($k) { return !is_null($k); });
        $this->tlsOptions = array_merge($this->tlsOptions, $opt);
    }
    
}

