<?php

namespace Artax\Http;

use StdClass,
    Traversable,
    LogicException,
    RuntimeException,
    InvalidArgumentException;

class StdResponse extends StdMessage implements Response {

    /**
     * @var string
     */
    protected $statusCode;

    /**
     * @var string
     */
    protected $statusDescription;

    /**
     * @var bool
     */
    protected $wasSent = false;
    
    /**
     * 
     */
    public function __construct(
        $statusCode,
        $statusDescription,
        $headers = array(),
        $body = '',
        $httpVersion = '1.1'
    ) {
        $this->statusCode = $statusCode;
        $this->statusDescription = $statusDescription;
        if ($headers) {
            $this->assignAllHeaders($headers);
        }
        $this->body = $body;
        $this->httpVersion = $httpVersion;
    }
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode();
        $msg.= ' ' . $this->getStatusDescription() . "\r\n";
        
        foreach ($this->getAllHeaders() as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        
        $msg.= "\r\n";
        $msg.= $this->getBodyStream() ? stream_get_contents($this->body) :$this->body;
        
        return $msg;
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
    public function getStatusDescription() {
        return $this->statusDescription;
    }

    /**
     * @return string
     */
    public function getStartLine() {
        return "HTTP/{$this->httpVersion} {$this->statusCode} {$this->statusDescription}";
    }

    /**
     * @return bool
     */
    public function wasSent() {
        return $this->wasSent;
    }

    /**
     * Formats and sends all headers prior to outputting the message body.
     * 
     * @return void
     * @throws RuntimeException
     */
    public function send() {
        $this->removeContentLengthForChunkedBody();
        $this->sendHeaders();
        if ($this->body) {
            $this->sendBody();
        }
        
        $this->wasSent = true;
    }
    
    /**
     * It's important not to send a Content-Length header with a streamed response body.
     * PHP automatically sends a chunked message if no Content-Length header is sent,
     * so we don't need to bother with adding a Transfer-Encoding header here.
     * 
     * @return void
     */
    protected function removeContentLengthForChunkedBody() {
        if ($this->getBodyStream() && $this->hasHeader('Content-Length')) {
            unset($this->headers['CONTENT-LENGTH']);
        }
    }
    
    /**
     * @return void
     */
    protected function sendHeaders() {
        $startLine = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode() . ' ';
        $startLine.= $this->getStatusDescription();
        
        header($startLine);
        
        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value);
        }
    }
    
    /**
     * @return void
     * @throws RuntimeException
     */
    protected function sendBody() {
        if (!is_resource($this->body)) {
            echo $this->body;
            return;
        }
        
        rewind($this->body);
        
        while (!feof($this->body)) {
            if (false !== ($chunk = $this->outputBodyChunk())) {
                echo $chunk;
            } else {
                throw new RuntimeException(
                    "Failed reading response body from {$this->body}"
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
