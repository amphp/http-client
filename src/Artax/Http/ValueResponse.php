<?php

namespace Artax\Http;

use Spl\DomainException;

/**
 * An immutable value object modeling an HTTP Response message
 */
class ValueResponse extends ValueMessage implements Response {

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $reasonPhrase = '';
    
    /**
     * @param string $protocol
     * @param int $status
     * @param string $reason
     * @param mixed $headers
     * @param mixed $body
     */
    public function __construct($protocol, $status, $reason = null, $headers = null, $body = null) {
        $this->assignProtocol($protocol);
        $this->assignStatusCode($status);
        
        if ($reason !== null) {
            $this->assignReasonPhrase($reason);
        }
        
        if ($headers !== null) {
            $this->appendAllHeaders($headers);
        }
        
        if ($body !== null) {
            $this->assignBody($body);
        }
    }
    
    /**
     * @param string $status
     * @throws Spl\DomainException
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
     * Reason-Phrase  = *<TEXT, excluding CR, LF>
     * TEXT           = <any OCTET except CTLs, but including LWS>
     * 
     * @param string $reason
     * @throws Spl\DomainException
     * @return void
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec6.html#sec6.1.1
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
     */
    protected function assignReasonPhrase($reason) {
        $reason = trim($reason);
        
        if ($reason === '') {
            return;
        }
        
        if (preg_match(",^[\x20-\x7e\x09]+$,", $reason)) {
            // replace multiple LWS with single space
            $this->reasonPhrase = preg_replace(",[\x20\t]+,", ' ', $reason);
        } else {
            throw new DomainException(
                "Invalid reason phrase; only ASCII 31-126 and ASCII 9 allowed"
            );
        }
    }

    /**
     * @return int
     */
    public function getStatusCode() {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    /**
     * Build a raw HTTP response start line (without trailing CRLF characters)
     * 
     * @return string
     */
    public function getStartLine() {
        return 'HTTP/' . $this->getProtocol() . ' ' . $this->statusCode . ' ' . $this->reasonPhrase;
    }
}