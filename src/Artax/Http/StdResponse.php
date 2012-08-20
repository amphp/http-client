<?php

namespace Artax\Http;

use RuntimeException,
    Artax\Http\Exceptions\MessageParseException;

class StdResponse extends StdMessage implements Response {

    /**
     * @var int
     */
    protected $statusCode = 200;

    /**
     * @var string
     */
    protected $statusDescription;

    /**
     * @var bool
     */
    protected $wasSent = false;
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = $this->getStartLine() . "\r\n";
        
        foreach ($this->headers as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        
        $msg.= "\r\n";
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
     * @return string
     */
    public function getStartLine() {
        return "HTTP/{$this->httpVersion} {$this->statusCode} {$this->statusDescription}";
    }
    
    /**
     * Parses and assigns start line values according to RFC 2616 Section 6.1
     * 
     * @param string $rawStartLineStr
     * @return void
     * @throws MessageParseException
     * @todo Determine if generic "InvalidFormatException" might be a better option
     */
    public function setStartLine($rawStartLineStr) {
        $startLinePattern = ',^HTTP/(\d+\.\d+) (\d{3}) (.+)$,';
        
        if (!preg_match($startLinePattern, trim($rawStartLineStr), $match)) {
            throw new MessageParseException(
                'Invalid HTTP start line: ' . trim($rawStartLineStr)
            );
        }
        
        $this->httpVersion = $match[1];
        $this->statusCode = $match[2];
        $this->statusDescription = $match[3];
    }

    /**
     * @return bool
     */
    public function wasSent() {
        return $this->wasSent;
    }

    /**
     * Formats and sends all headers AND outputs the message entity body.
     * 
     * @return void
     * @throws RuntimeException
     */
    public function send() {
        $this->sendHeaders();
        
        if ($this->body) {
            $this->sendBody();
        }
        
        $this->wasSent = true;
    }
    
    /**
     * Formats and sends all headers
     * 
     * @return void
     */
    protected function sendHeaders() {
        $this->normalizeContentLengthForSend();
        
        header($this->getStartLine());
        
        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value);
        }
    }
    
    /**
     * Ensure Content-Length header is appropriate given the message entity body
     * 
     * PHP will automatically chunk the response output if no Content-Length header is sent by the
     * user. This is desirable behavior when using a streaming entity body and is protected by
     * removing the Content-Length header when the entity body is a stream resource. Otherwise,
     * we ensure that a valid Content-Length header is specified for non-streaming entity bodies.
     * 
     * @return void
     */
    protected function normalizeContentLengthForSend() {
        if ($this->body && !$this->getBodyStream()) {
            $this->setHeader('Content-Length', strlen($this->body));
        } else {
            $this->removeHeader('Content-Length');
        }
    }
    
    /**
     * @return void
     * @throws RuntimeException
     */
    protected function sendBody() {
        if (!$entityBodyStream = $this->getBodyStream()) {
            echo $this->body;
            return;
        }
        
        while (!feof($entityBodyStream)) {
            if (false !== ($bodyChunk = $this->outputBodyChunk())) {
                echo $bodyChunk;
            } else {
                throw new RuntimeException(
                    "Failed reading response body from $entityBodyStream"
                );
            }
        }
    }
    
    /**
     * @return int Returns false on error
     */
    protected function outputBodyChunk() {
        return @fread($this->body, 8192);
    }
}
