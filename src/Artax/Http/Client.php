<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException;

class Client {
    
    /**
     * @var int
     */
    protected $maxRedirects = 10;
    
    /**
     * @var int
     */
    protected $currentRedirectIteration = 0;
    
    /**
     * @var bool
     */
    protected $nonStandardRedirectFlag = false;
    
    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var array
     */
    protected $responseChain;
    
    /**
     * @var array
     */
    protected $redirectHistory;
    
    /**
     * @var bool
     */
    protected $isOpenSslLoaded;
    
    /**
     * @var array
     */
    protected $sslOptions = array();

    public function __construct() {
        $this->timeout = ini_get('default_socket_timeout');
        $this->isOpenSslLoaded = $this->getOpenSslStatus();
    }
    
    /**
     * @return bool
     */
    protected function getOpenSslStatus() {
        return extension_loaded('openssl');
    }

    /**
     * Set the number of seconds before a request times out.
     * 
     * @param int $seconds
     * @return void
     */
    public function setTimeout($seconds) {
        $this->timeout = (int) $seconds;
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
    public function setSslOptions($options) {
        $this->sslOptions = $options;
    }

    /**
     * Set the maximum number of redirects allowed to fulfill a request. Defaults to 10.
     * 
     * @param int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->maxRedirects = (int) $maxRedirects;
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
     * Request an HTTP resource
     * 
     * @param Request $request
     * @return Response
     * @throws RuntimeException
     */
    public function request(Request $request) {
        $this->responseChain = array();
        $this->redirectHistory = array();
        return $this->doRequest($request);
    }
    
    /**
     * Request an HTTP resource, returning an array of Response objects created by redirection
     * 
     * @param Request $request
     * @return array
     */
    public function requestChain(Request $request) {
        $this->request($request);
        return $this->responseChain;
    }
    
    /**
     * Send an HTTP request and don't wait for a response
     * 
     * @param Request $request
     * @return void
     */
    public function requestAsync(Request $request) {
        $stream = $this->buildSocketStream(
            $request, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT
        );
        
        fwrite($stream, $request->__toString());
        fclose($stream);
    }

    /**
     * @param Request $request
     * @param int $flags
     * @return resource
     * @throws RuntimeException
     */
    protected function buildSocketStream(Request $request, $flags = STREAM_CLIENT_CONNECT) {
        $scheme = strcmp('https', $request->getScheme()) ? 'tcp' : 'ssl';
        $authority = $request->getHost() . ':' . $request->getPort();
        
        if ($scheme == 'tcp') {
            return $this->makeTcpStream("tcp://$authority", $flags);
        } else {
            return $this->makeSslStream("ssl://$authority", $flags);
        }
    }

    /**
     * @todo Add error handling for different failure types (length/md5 mismatch, empty, etc)
     * @param Request $request
     * @return Response
     * @throws RuntimeException
     */
    protected function doRequest(Request $request) {
        $stream = $this->buildSocketStream($request);
        
        fwrite($stream, $request->__toString());
        
        $rawResponseMessage = '';
        while (!feof($stream)) {
            $rawResponseMessage.= fread($stream, 8192);
        }
        fclose($stream);
        
        $response = new StdResponse();
        $response->populateFromRawMessage($rawResponseMessage);
        
        $this->responseChain[] = $response;
        
        if ($this->canRedirect($request, $response)) {
            return $this->doRedirect($request, $response);
        } else {
            return $response;
        }
    }
    
    /**
     * @todo Determine appropriate exception to throw on stream failure
     * @param string $uri
     * @param int $flags
     * @return resource
     * @throws RuntimeException
     */
    protected function makeTcpStream($uri, $flags) {
        $stream = @stream_socket_client(
            $uri,
            $errorNo,
            $errorStr,
            $this->timeout,
            $flags
        );
        
        if (false === $stream) {
            throw new RuntimeException(
                "Connection to $socketUri failed: [error $errorNo] $errorStr"
            );
        }
        
        return $stream;
    }
    
    /**
     * @todo Determine appropriate exception to throw on stream failure
     * @param string $uri
     * @param int $flags
     * @return resource
     * @throws RuntimeException
     */
    protected function makeSslStream($uri, $flags) {
        if (!$this->isOpenSslLoaded) {
            throw new RuntimeException(
                '`openssl` extension must be loaded to make SSL requests'
            );
        }
        
        $context = stream_context_create(array('ssl'=>$this->sslOptions));
        $stream = @stream_socket_client(
            $uri,
            $errNo,
            $errStr,
            $this->timeout,
            $flags,
            $context
        );
        
        if (false === $stream) {
            throw new RuntimeException(
                "Connection to $uri failed: [error $error] $errStr"
            );
        }
        
        return $stream;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    protected function canRedirect(Request $request, Response $response) {
        if ($this->currentRedirectIteration >= $this->maxRedirects) {
            return false;
        }
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        $requestMethod = strtoupper($request->getMethod());
        if (!$this->nonStandardRedirectFlag && !in_array($requestMethod, array('GET', 'HEAD'))) {
            return false;
        }
        
        return true;
    }

    /**
     * @param Request $lastRequest
     * @param Response $lastResponse
     * @return Response
     */
    protected function doRedirect(Request $lastRequest, Response $lastResponse) {
        $this->redirectHistory[] = $lastRequest->getRawUri();
        $newLocation = $this->normalizeLocationHeader($lastRequest, $lastResponse);
        
        if (in_array($newLocation, $this->redirectHistory)) {
            throw new InfiniteRedirectException(
                "Infinite redirect detected: $newLocation"
            );
        }
        
        $redirectedRequest = new StdRequest(
            $newLocation,
            $lastRequest->getMethod(),
            $lastRequest->getAllHeaders(),
            $lastRequest->getBody(),
            $lastRequest->getHttpVersion()
        );
        
        ++$this->currentRedirectIteration;
        
        return $this->doRequest($redirectedRequest);
    }

    /**
     * @param Request $lastRequest
     * @param Response $lastResponse
     * @return string
     */
    protected function normalizeLocationHeader(Request $lastRequest, Response $lastResponse) {
        $locationHeader = $lastResponse->getHeader('Location');
        
        if (!@parse_url($locationHeader,  PHP_URL_HOST)) {
            $newLocation = $lastRequest->getScheme() . '://' . $lastRequest->getRawAuthority();
            $newLocation.= '/' . ltrim($locationHeader, '/');
            $lastResponse->setHeader(
                'Warning',
                "299 Invalid Location header: $locationHeader; $newLocation assumed"
            );
        } else {
            $newLocation = $locationHeader;
        }
        
        return $newLocation;
    }
    
}
