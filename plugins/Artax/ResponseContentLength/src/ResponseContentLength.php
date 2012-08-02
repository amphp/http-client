<?php
/**
 * ResponseContentLength Class File
 * 
 * @category    ArtaxPlugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace ArtaxPlugins;

use Artax\Http\Response;

/**
 * Applies Content-Length header to HTTP responses
 * 
 * @category    ArtaxPlugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ResponseContentLength {
    
    /**
     * @param Response $response
     * @return void
     */
    public function __invoke(Response $response) {
        $this->setContentLengthHeader($response);
    }
    
    /**
     * @param Response $response
     * @return void
     */
    public function setContentLengthHeader(Response $response) {
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.3
        // 
        // "The presence of a message-body in a request is signaled by the inclusion of a
        // Content-Length  or Transfer-Encoding header field in the request's message-headers."
        //
        // http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.4
        // 
        // "If a message is received with both a Transfer-Encoding header field and a Content-Length
        // header field, the latter MUST be ignored."
        if (!$response->hasHeader('TransferEncoding') && $body = $response->getBody()) {
            $contentLength = strlen($body);
            $response->setHeader('Content-Length', $contentLength);
        }
    }
}
