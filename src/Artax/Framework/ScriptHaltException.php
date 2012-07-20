<?php
/**
 * ScriptHaltException Class File
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
 * Exception purposefully thrown to end script execution
 * 
 * This exception makes testing code much simpler. Instead of calling `die`
 * or `exit` directly, methods can throw a `ScriptHaltException` which is
 * an easily testable outcome. The built-in Artax Termination handler class
 * will exit quietly in the event of an uncaught `ScriptHaltException`.
 * 
 * @category    Artax
 * @package     Framework
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class ScriptHaltException extends RuntimeException {}
