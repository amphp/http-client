<?php
/**
 * HTTP ProxyRequest Class File
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

/**
 * An immutable value object modeling requests made to HTTP proxies
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ProxyRequest extends StdRequest {
    
    /**
     * Returns a fully stringified HTTP request message to be sent to a proxy server
     * 
     * The request line differ slightly for requests to proxies as per rfc2616-5.1.2:
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec5.html#sec5
     * 
     * @return string
     */
    protected function buildMessage() {
        $msg = $this->getMethod() . ' ' . $this->getRawUri() . ' ';
        $msg.= 'HTTP/' . $this->getHttpVersion() . "\r\n";
        
        foreach ($this->getAllHeaders() as $header => $value) {
            $msg.= "$header: $value\r\n";
        }
        $msg.= "\r\n" . $this->getBody();
        
        return $msg;
    }
}
