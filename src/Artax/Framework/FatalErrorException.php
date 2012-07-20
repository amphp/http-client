<?php
/**
 * FatalErrorException Class File
 * 
 * @category    Artax
 * @package     Framework
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework;

use RuntimeException;

/**
 * Internal exception used when a fatal E_ERROR terminates script execution
 * 
 * This exception is used internally and should never be thrown manually. If purposeful 
 * script termination mimicking normal shutdown is desired, the `ScriptHaltException` 
 * should be thrown instead.
 * 
 * @category    Artax
 * @package     Framework
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class FatalErrorException extends RuntimeException {}
