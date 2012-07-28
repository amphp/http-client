<?php
/**
 * ContentLengthApplier Class File
 * 
 * @category    ArtaxPlugins
 * @package     ResponseContentLength
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace ArtaxPlugins\ResponseContentLength;

use Artax\Http\Response;

/**
 * Applies Content-Length header to HTTP responses
 * 
 * @category    Artax
 * @package     ResponseContentLength
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ContentLengthApplier {
    
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
