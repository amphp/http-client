<?php
/**
 * HttpStatusException Class File
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
 * Exception thrown to initiate specific HTTP status responses
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class HttpStatusException extends RuntimeException {
    
    /**
     * @param string $statusMessage
     * @param int $code
     * @param Exception $previous
     * @return void
     * @throws DomainException
     */
    public function __construct($statusMessage, $code, Exception $previous = null) {
        if (!is_int($code) || !($code >= 100 && $code <= 599)) {
            throw new DomainException(
                'HttpStatusException objects require a valid HTTP status code (100-599)'
            );
        }
        parent::__construct($statusMessage, $code, $previous);
    }
}
