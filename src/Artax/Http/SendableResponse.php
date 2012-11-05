<?php

namespace Artax\Http;

use Spl\ValueException;

class SendableResponse extends StdResponse {

    /**
     * @var bool
     */
    protected $wasSent = false;

    /**
     * Formats/sends all headers and the response message entity body.
     * 
     * @return void
     */
    public function send() {
        $this->normalizeHeadersForSend();
        $this->sendHeaders();
        
        if (!empty($this->body)) {
            $this->sendBody();
        }
        
        $this->wasSent = true;
    }
    
    protected function normalizeHeadersForSend() {
        if ($this->getBodyStream()) {
            $this->setHeader('Transfer-Encoding', 'chunked');
            $this->removeHeader('Content-Length');
        } elseif ($this->body) {
            $this->setHeader('Content-Length', strlen($this->body));
            $this->removeHeader('Transfer-Encoding');
        } else {
            $this->removeHeader('Content-Length');
            $this->removeHeader('Transfer-Encoding');
        }
    }
    
    protected function sendHeaders() {
        header($this->getStartLine());
        foreach ($this->headers as $header) {
            /**
             * @var Header $header
             */
            $header->send();
        }
        flush();
    }
    
    protected function sendBody() {
        $entityBodyStream = $this->getBodyStream();
        if (empty($entityBodyStream)) {
            echo $this->body;
            return;
        }
        
        while (!feof($entityBodyStream)) {
            if ($bodyChunk = @fread($this->body, 4096)) {
                $chunkLength = strlen($bodyChunk);
                echo dechex($chunkLength) . "\r\n$bodyChunk\r\n";
                flush();
            }
        }
        
        echo "0\r\n\r\n";
    }

    /**
     * Has this response been sent?
     * 
     * @return bool
     */
    public function wasSent() {
        return $this->wasSent;
    }
}