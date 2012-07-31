<?php

namespace Artax\Http;

use RuntimeException,
    InvalidArgumentException;

class Client {

    /**
     * @var array
     */
    protected $contextOptions = array('http' => array(
        'max_redirects' => 10,
        'ignore_errors' => TRUE,
        'follow_location' => 0 // off to allow manual redirection
    ));
    
    /**
     * @var bool
     */
    protected $allowUrlFopen;
    
    /**
     * @var bool
     */
    protected $followLocation = true;
    
    /**
     * @var bool
     */
    protected $nonStandardRedirects = false;

    /**
     * @var array
     */
    protected $redirectChain;
    
    /**
     * @return void
     */
    public function __construct() {
        $this->allowUrlFopen = $this->getAllowUrlFopenStatus();
    }
    
    /**
     * @return bool
     */
    protected function getAllowUrlFopenStatus()  {
        return filter_var(
            ini_get('allow_url_fopen'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Should Location headers be used for automatically redirection? Defaults to true.
     * 
     * @param int $boolFlag
     * @return void
     */
    public function setFollowLocation($boolFlag) {
        $this->followLocation = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Maximum number of allowed redirects. Defaults to 10
     * 
     * @param int $maxRedirects
     * @return void
     */
    public function setMaxRedirects($maxRedirects) {
        $this->contextOptions['http']['max_redirects'] = $maxRedirects;
    }
    
    /**
     * Is automatic redirection of requests not using GET or HEAD allowed? Defaults to false.
     * 
     * RFC2616-10.3:
     * "If the 301 status code is received in response to a request other than GET or HEAD, the user
     *  agent MUST NOT automatically redirect the request unless it can be confirmed by the user, 
     * since this might change the conditions under which the request was issued."
     * 
     * This directive allows the user to confirm that requests made using methods other than GET
     * and HEAD may be redirected.
     * 
     * @param bool $boolFlag
     * @return void
     */
    public function setNonStandardRedirects($boolFlag) {
        $this->nonStandardRedirects = filter_var($boolFlag, FILTER_VALIDATE_BOOLEAN);
    }
    
    /**
     * Request a remote HTTP resource
     * 
     * @return Artax\Http\Response
     * @throws RuntimeException
     */
    public function request(Request $request) {
        $this->redirectChain = array();
        return $this->doRequest($request);
    }
    
    /**
     * Request an HTTP resource, returning an array of Response objects created by redirection
     * 
     * @param Artax\Http\Request $request
     * @return array
     */
    public function requestRedirectChain(Request $request) {
        $this->request($request);
        return $this->redirectChain;
    }
    
    /**
     * @return Artax\Http\Response
     * @throws RuntimeException
     */
    protected function doRequest(Request $request) {
        if (!$this->allowUrlFopen) {
            throw new RuntimeException(
                '`allow_url_fopen` must be enabled to use Artax\\Http\\Client'
            );
        }
        
        $context = $this->buildStreamContext($request);
        $stream  = $this->buildStream($request->getRawUri(), $context);
        
        if ($stream === FALSE) {
            throw new RuntimeException();
        }
        
        $bodyData = $this->getStreamBodyData($stream);
        $metaData = $this->getStreamMetaData($stream);
        $response = $this->buildResponse($metaData, $bodyData);
        
        $this->redirectChain[] = $response;
        
        if ($this->canRedirect($request, $response)) {
            return $this->doRedirect($request, $response);
        } else {
            return $response;
        }
    }
    
    /**
     * @return resource
     */
    protected function buildStreamContext(Request $request) {
        if ($headers = $request->getAllHeaders()) {
            $streamFormattedHeaders = array();
            foreach($headers as $header => $value) {
                $streamFormattedHeaders[] = "$header: $value";
            }
            $this->contextOptions['http']['header'] = $streamFormattedHeaders;
        }

        $this->contextOptions['http']['content'] = $request->getBody();
        $this->contextOptions['http']['method'] = $request->getMethod();
        $this->contextOptions['http']['protocol_version'] = $request->getHttpVersion();
        
        return stream_context_create($this->contextOptions);
    }
    
    /**
     * @param string $uri
     * @param resource $context
     * @return resource
     */
    protected function buildStream($uri, $context) {
        return @fopen($uri, 'rb', $useIncludePath = FALSE, $context);
    }
    
    /**
     * @param resource $stream
     * @return array
     */
    protected function getStreamMetaData($stream) {
        $metaData = stream_get_meta_data($stream);
        return $metaData['wrapper_data'];
    }
    
    /**
     * @param resource $stream
     * @return string
     */
    protected function getStreamBodyData($stream) {
        return stream_get_contents($stream);
    }

    /**
     * @todo Add more error handling
     * @param array $headers
     * @param string $body
     * @return Artax\Http\Response
     */
    protected function buildResponse($headers, $body) {
        $response = new StdResponse();
        
        foreach ($headers as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $response->setStartLine($header);
                $headers[] = array($header);
            } else {
                $response->setRawHeader($header);
            }
        }

        $response->setBody($body);

        return $response;
    }
    
    /**
     * @return bool
     */
    protected function canRedirect(Request $request, Response $response) {
        if (!$this->followLocation) {
            return false;
        }
        
        $statusCode = $response->getStatusCode();
        
        if ($statusCode < 300) {
            return false;
        }
        if ($statusCode > 399) {
            return false;
        }
        if (!$response->hasHeader('Location')) {
            return false;
        }
        
        $requestMethod = strtolower($request->getMethod());
        
        if (!$this->nonStandardRedirects && !in_array($requestMethod, array('get', 'head'))) {
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
}

