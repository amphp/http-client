<?php
/**
 * BadResourceMethodException Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */ 
namespace Artax\Framework\Routing;

use RuntimeException;

/**
 * Exception thrown when a resource doesn't expose the requested method during verb mapping
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Routing
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class BadResourceMethodException extends RuntimeException {
    
    /**
     * @var array
     */
    private $availableMethods;
    
    /**
     * @param array $availableMethods
     * @return void
     */
    public function __construct(array $availableMethods) {
        $this->availableMethods = $availableMethods;
    }
    
    /**
     * @return array
     */
    public function getAvailableMethods() {
        return $this->availableMethods;
    }
}
