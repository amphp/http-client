<?php
/**
 * HTTP StdResponse Class File
 * 
 * @category     Artax
 * @package      Http
 * @author       Levi Morrison <levim@php.net>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Http;

use StdClass,
    Traversable,
    RuntimeException,
    InvalidArgumentException,
    Artax\Events\Mediator;

/**
 * A standard mutable HTTP response model
 * 
 * @category     Artax
 * @package      Routing
 * @author       Levi Morrison <levim@php.net>
 */
class StdResponse implements Response {

    /**
     * @var string
     */
    private $httpVersion = '1.1';

    /**
     * @var string
     */
    private $statusCode = '200';

    /**
     * @var string
     */
    private $statusDescription = 'OK';

    /**
     * @var array
     */
    private $headers = array();
    
    /**
     * @var string
     */
    private $body = '';

    /**
     * @var bool
     */
    private $wasSent = FALSE;

    /**
     * @return string The HTTP version number (not prefixed by `HTTP/`)
     */
    public function getHttpVersion() {
        return $this->httpVersion;
    }

    /**
     * @param string $httpVersion
     * @return string
     */
    public function setHttpVersion($httpVersion) {
        $this->httpVersion = $httpVersion;
    }

    /**
     * @return int
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @param string $httpStatusCode
     * @return void
     */
    public function setStatusCode($httpStatusCode) {
        $this->statusCode = $httpStatusCode;
    }

    /**
     * @return string
     */
    public function getStatusDescription() {
        return $this->statusDescription;
    }

    /**
     * @param string $httpStatusDescription
     * @return void
     */
    public function setStatusDescription($httpStatusDescription) {
        $this->statusDescription = $httpStatusDescription;
    }

    /**
     * @param string $headerName
     * @return string
     * @throws RuntimeException
     * @todo Figure out the best exception to throw. Perhaps one isn't needed.
     */
    public function getHeader($headerName) {
        if (!$this->hasHeader($headerName)) {
            throw new RuntimeException();
        }
        
        // Headers are case-insensitive:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        return $this->headers[$capsHeader];
    }

    /**
     * @param string $headerName
     * @return bool
     */
    public function hasHeader($headerName) {
        // Headers are case-insensitive:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        return isset($this->headers[$capsHeader]) || array_key_exists($capsHeader, $this->headers);
    }

    /**
     * @return array
     */
    public function getAllHeaders() {
        return $this->headers;
    }

    /**
     * @param string $headerName
     * @param string $value
     * @return void
     */
    public function setHeader($headerName, $value) {
        // Headers are case-insensitive as per the HTTP spec:
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2
        $capsHeader = strtoupper($headerName);
        $this->headers[$capsHeader] = $value;
    }
    
    /**
     * @param mixed $iterable
     * @return void
     * @throws InvalidArgumentException
     */
    public function setAllHeaders($iterable) {
        if (!($iterable instanceof Traversable
            || $iterable instanceof StdClass
            || is_array($iterable)
        )) {
            throw new InvalidArgumentException(
                'Argument 1 passed to '.get_class($this).'::setAllHeaders must '
                .'be an array, StdClass or Traversable object'
            );
        }
        foreach ($iterable as $headerName => $value) {
            $this->setHeader($headerName, $value);
        }
    }

    /**
     * @return string
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @param string $body
     * @return void
     * @notifies sys.response.set-body(StdResponse $response)
     */
    public function setBody($bodyString) {
        $this->body = $bodyString;
    }

    /**
     * Formats and sends all headers prior to outputting the message body.
     * @return void
     * @notifies sys.response.before-send(StdResponse $response)
     * @notifies sys.response.after-send(StdResponse $response)
     */
    public function send() {
        $headerStr = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode() . ' ' .
            $this->getStatusDescription();
        
        $this->sendHeader($headerStr);
        
        foreach ($this->headers as $header => $value) {
            $this->sendHeader($header . ': ' . $value);
        }

        echo $this->body;
        
        $this->wasSent = true;
    }
    
    /**
     * A testing seam to mock header output in test environments
     * @param string $headerString
     */
    protected function sendHeader($headerString) {
        header($headerString);
    }

    /**
     * @return bool
     */
    public function wasSent() {
        return $this->wasSent;
    }
}
