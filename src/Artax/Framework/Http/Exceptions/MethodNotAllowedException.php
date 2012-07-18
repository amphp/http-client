<?php
/**
 * MethodNotAllowedException Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Http\Exceptions;

use Exception,
    DomainException,
    RuntimeException;

/**
 * Exception used to initiate an HTTP `405 Method Not Allowed` response
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class MethodNotAllowedException extends HttpStatusException {
    
    /**
     * @var array
     */
    private $availableResourceMethods;
    
    /**
     * @param array $availableResourceMethods
     * @return void
     */
    public function __construct(array $availableResourceMethods) {
        // As per http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.7 ...
        // An Allow header field MUST be present in a 405 (Method Not Allowed) response
        $this->availableResourceMethods = $availableResourceMethods;
        parent::__construct('Method Not Allowed', 405);
    }
    
    /**
     * @return array
     */
    public function getAvailableResourceMethods() {
        return $this->availableResourceMethods;
    }
}
