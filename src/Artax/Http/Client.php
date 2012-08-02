<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException;

class Client {

    /**
     * @var bool
     */
    protected $followLocation = true;
    
    /**
     * @var int
     */
    protected $maxRedirects = 10;
    
    /**
     * @var bool
     */
    protected $nonStandardRedirects = false;
    
    /**
     * @var int
     */
    protected $currentRedirectIteration = 0;

    /**
     * @var array
     */
    protected $responseChain;
    
    /**
     * @var bool
     */
    protected $openSslLoaded;
    
    /**
     * @return void
     */
    public function __construct() {
        $this->openSslLoaded = $this->isOpenSslLoaded();
    }
    
    /**
     * @return bool
     */
    protected function isOpenSslLoaded() {
        return extension_loaded('openssl');
    }
    
    /**
     * Request a remote HTTP resource
     * 
     * @param Artax\Http\Request $request
     * @return Artax\Http\Response
     * @throws RuntimeException
     */
    public function request(Request $request) {
        $this->responseChain = array();
        return $this->doRequest($request);
    }
    
    /**
     * Request an HTTP resource, returning an array of Response objects created by redirection
     * 
     * @param Artax\Http\Request $request
     * @return array
     */
    public function requestChain(Request $request) {
        $this->request($request);
        return $this->responseChain;
    }
    
    /**
     * Send a request and don't wait for a response
     * 
     * @param Artax\Http\Request $request
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
     * @return Artax\Http\Response
     * @throws RuntimeException
     */
    protected function buildSocketStream(Request $request, $flags = STREAM_CLIENT_CONNECT) {
        $transport = strcmp('https', $request->getScheme()) ? 'tcp' : 'ssl';
        
        if ('ssl' === $transport && !$this->openSslLoaded) {
            throw new RuntimeException(
                '`openssl` extension must be loaded to make SSL requests'
            );
        }
        
        $socketUri = "$transport://" . $request->getHost() . ':' . $request->getPort();
        $timeOut = 5;
        
        $context = stream_context_create();
        stream_context_set_params($context, array('notification' =>
            array($this, 'notifyCallback')
        ));
        
        $stream = stream_socket_client($socketUri, $errorNo, $errorStr, $timeOut, $flags, $context);
        
        if (false === $stream) {
            throw new RuntimeException(
                "Asynchronous connection failed [$errorNo]: $errorStr"
            );
        }
        
        return $stream;
    }
    
    /**
     * @return Artax\Http\Response
     * @throws RuntimeException
     */
    protected function doRequest(Request $request) {
        $stream = $this->buildSocketStream($request);
        fwrite($stream, $request->__toString());
        
        $rawResponseMessage = '';
        while (!feof($stream)) {
            $rawResponseMessage.= fgets($stream, 1024);
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
     * @return bool
     */
    protected function canRedirect(Request $request, Response $response) {
        if (!$this->followLocation) {
            return false;
        }
        if ($this->currentRedirectIteration == $this->maxRedirects) {
            return false;
        }
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        $requestMethod = strtoupper($request->getMethod());
        if (!$this->nonStandardRedirects && !in_array($requestMethod, array('GET', 'HEAD'))) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @return Artax\Http\Response
     */
    protected function doRedirect(Request $lastRequest, Response $lastResponse) {
        $newLocation = $this->normalizeLocationHeader($lastRequest, $lastResponse);
        
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

    /**
     * Should Location headers transparently redirect? Defaults to true.
     * 
     * @param bool $boolFlag
     * @return void
     */
    public function followLocation($boolFlag) {
        $this->followLocation = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Should the client transparently redirect equests not using GET or HEAD? Defaults to false.
     * 
     * Acording to RFC2616-10.3, "If the 301 status code is received in response to a request other
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
        $this->nonStandardRedirects = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
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
    
    protected function notifyCallback(
        $notification_code, $severity, $message,
        $message_code, $bytes_transferred, $bytes_max
    ) {
        echo "$notification_code, $severity, $message, $message_code, $bytes_transferred, $bytes_max\n";
    }
}

