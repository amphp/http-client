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
        $contentLength = strlen($response->getBody());
        $response->setHeader('Content-Length', $contentLength);
    }
}
