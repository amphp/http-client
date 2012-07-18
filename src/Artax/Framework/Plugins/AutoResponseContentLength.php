<?php
/**
 * AutoResponseContentLength Plugin Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Plugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Plugins;

use Artax\Http\Response;

/**
 * Applies Content-Length header to HTTP responses
 * 
 * This plugin can be registered as a listener for the `sys.response.beforeSend` event
 * to automatically assign a Content-Length header to all Response objects prior to output.
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Plugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AutoResponseContentLength {
    
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
