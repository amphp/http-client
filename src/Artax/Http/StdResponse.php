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
     * Formats and sends all headers prior to outputting the message body.
     * 
     * @return void
     * @todo add chunked response body streaming
     */
    public function send() {
        $startLine = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode() . ' ';
        $startLine.= $this->getStatusDescription();
        header($startLine);
        
        foreach ($this->headers as $header => $value) {
            header($header . ': ' . $value);
        }
        
        if (!$this->body) {
            $this->wasSent = true;
            return;
        } elseif ($streamableBody = $this->getBodyStream()) {
            echo stream_get_contents($streamableBody);
        } else {
            echo $this->body;
        }
        
        $this->wasSent = true;
    }

    /**
     * @return bool
     */
    public function wasSent() {
        return $this->wasSent;
    }
    
    /**
     * @return string
     */
    public function __toString() {
        $msg = 'HTTP/' . $this->getHttpVersion() . ' ' . $this->getStatusCode();
        $msg.= ' ' . $this->getStatusDescription() . "\r\n";
        
        $headerArr = $this->getAllHeaders();
        $headers = array_combine(
            array_map('strtoupper', array_keys($headerArr)),
            array_values($headerArr)
        );
        
        foreach ($headers as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        
        $msg.= "\r\n" . $this->getBody();
        
        return $msg;
    }
}
