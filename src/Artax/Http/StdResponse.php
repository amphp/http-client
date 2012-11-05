<?php

namespace Artax\Http;

use Spl\ValueException;

class StdResponse extends StdMessage implements Response {

    /**
     * @var int
     */
    protected $statusCode = 200;

    /**
     * @var string
     */
    protected $reasonPhrase;
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = $this->getStartLineAndHeaders();
        $msg.= $this->getBody();
        
        return $msg;
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
        $this->statusCode = (int) $httpStatusCode;
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
        $this->reasonPhrase = $httpStatusDescription;
    }

    /**
     * Build a raw HTTP response start line (without trailing CRLF)
     * 
     * @return string
     */
    public function getStartLine() {
        return "HTTP/{$this->httpVersion} {$this->statusCode} {$this->reasonPhrase}";
    }
    
    /**
     * Parses and assigns start line values according to RFC 2616 Section 6.1
     * 
     * @param string $rawStartLineStr
     * @return void
     * @throws \Spl\ValueException
     */
    public function setStartLine($rawStartLineStr) {
        $startLinePattern = ',^HTTP/(\d+\.\d+) (\d{3}) (.+)$,';
        
        if (!preg_match($startLinePattern, trim($rawStartLineStr), $match)) {
            throw new ValueException(
                'Invalid HTTP start line: ' . $rawStartLineStr
            );
        }
        
        $this->httpVersion = $match[1];
        $this->statusCode = $match[2];
        $this->reasonPhrase = $match[3];
    }
}