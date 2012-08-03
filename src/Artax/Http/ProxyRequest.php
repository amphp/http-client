<?php

namespace Artax\Http;

class ProxyRequest extends StdRequest {
    
    /**
     * Builds a raw HTTP request message for sending to a proxy server
     * 
     * The request line differ slightly for requests to proxies as per rfc2616-5.1.2:
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
     * 
     * @return string
     */
    protected function buildMessage() {
        $msg = $this->getMethod() . ' ' . $this->getUri() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion() . "\r\n";
        
        if ($body = $this->getBody()) {
            $msg.= 'CONTENT-LENGTH: ' . strlen($body) . "\r\n";
        }
        
        $headers = $this->getAllHeaders();
        unset($headers['CONTENT-LENGTH']);
        
        foreach ($headers as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        $msg.= "\r\n" . $this->getBody();
        
        return $msg;
    }
}
