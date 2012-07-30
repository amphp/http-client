<?php
/**
 * ResponseDate class file
 * 
 * @category    ArtaxPlugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace ArtaxPlugins;

use Artax\Http\Response;

/**
 * ApplY RFC 1123 Date header to an HTTP response
 * 
 * rfc2616-sec14.18: "Origin servers MUST include a Date header field in all responses ...
 * it MUST be sent in RFC 1123 date format."
 * 
 * @category    ArtaxPlugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ResponseDate {
    
    /**
     * @param Response $response
     * @return void
     */
    public function __invoke(Response $response) {
        $this->setDateHeader($response);
    }
    
    /**
     * @param Response $response
     * @return void
     */
    public function setDateHeader(Response $response) {
        $rf1123Date = $this->buildRfc1123Date();
        $response->setHeader('Date', $rf1123Date);
    }
    
    /**
     * @return string
     */
    protected function buildRfc1123Date() {
        return date('D, d M Y H:i:s e');
    }
}
