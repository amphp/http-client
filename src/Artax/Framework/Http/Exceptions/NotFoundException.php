<?php
/**
 * NotFoundException Class File
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
 * Exception used to initiate an HTTP `404 Not Found` response
 * 
 * This is simply a convenience class and is equivalent to an HttpStatusException with the
 * relevant 404 error code and "Not Found" HTTP status message.
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class NotFoundException extends HttpStatusException {
    
    public function __construct() {
        parent::__construct('Not Found', 404);
    }
}
