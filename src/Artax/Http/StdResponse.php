<?php

namespace Artax\Http;

use Ardent\TypeException,
    Ardent\DomainException;

/**
 * A mutable object used to generate HTTP Responses
 */
class StdResponse extends StdMessage implements MutableResponse {

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $reasonPhrase;

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
        $this->assignStatusCode($httpStatusCode);
    }
    
    /**
     * @param string $status
     * @throws Ardent\DomainException On invalid status code
     * @return void
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6.1.1
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
     */
    protected function assignStatusCode($status) {
        $status = trim($status);
        
        if (preg_match(",^[1-5]\d\d$,", $status)) {
            $this->statusCode = (int) $status;
        } else {
            throw new DomainException(
                "Invalid status code: status codes must be a three-digit integer [100-599]"
            );
        }
    }

    /**
     * @return string
     */
    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    /**
     * @param string $httpStatusDescription
     * @return void
     */
    public function setReasonPhrase($httpStatusDescription) {
        $this->assignReasonPhrase($httpStatusDescription);
    }
    
    /**
     * Reason-Phrase  = *<TEXT, excluding CR, LF>
     * TEXT           = <any OCTET except CTLs, but including LWS>
     * 
     * @param string $reason
     * @throws Ardent\DomainException On invalid reason phrase
     * @return void
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6.1.1
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
     */
    protected function assignReasonPhrase($reason) {
        $reason = trim($reason);
        
        if ($reason === '') {
            return;
        } elseif (preg_match(",^[\x20-\x7e\x09]+$,", $reason)) {
            // replace multiple LWS with single space
            $this->reasonPhrase = preg_replace(",[\x20\t]+,", ' ', $reason);
        } else {
            throw new DomainException(
                "Invalid reason phrase; only ASCII 31-126 and ASCII 9 allowed"
            );
        }
    }

    /**
     * Build a raw HTTP response start line (without trailing CRLF)
     * 
     * @return string
     */
    public function getStartLine() {
        return 'HTTP/' .$this->getProtocol(). ' ' .$this->statusCode. ' ' .$this->reasonPhrase;
    }
    
    /**
     * @param Response $response
     * @return void
     */
    public function import($response) {
        if (!$response instanceof Response) {
            throw new TypeException(
                get_class($this) . '::import requires an instance of Artax\\Http\\Response at ' .
                'Argument 1'
            );
        }
        
        $this->setProtocol($response->getProtocol());
        $this->setStatusCode($response->getStatusCode());
        $this->setReasonPhrase($response->getReasonPhrase());
        $this->setAllHeaders($response->getAllHeaders());
        $this->setBody($response->getBody());
    }
    
    /**
     * Export an immutable ValueResponse from the current instance
     * 
     * @throws \Ardent\DomainException If protocol or status code not set
     * @return ValueResponse
     */
    public function export() {
        if (!($this->getProtocol() && $this->getStatusCode())) {
            throw new DomainException(
                "Protocol and status code must be assigned prior to exporting a response"
            );
        }
        
        return new ValueResponse(
            $this->getProtocol(),
            $this->getStatusCode(),
            $this->getReasonPhrase(),
            $this->getAllHeaders(),
            $this->getBody()
        );
    }
}