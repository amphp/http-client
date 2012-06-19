<?php

/**
 * Artax FatalErrorException File
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    ${license.txt}
 * @version    ${project.version}
 */

namespace Artax;
use RuntimeException;

/**
 * Internal exception used when a fatal E_ERROR terminates script execution
 * 
 * The internal Termination handler generates this exception in the event of
 * a fatal `E_ERROR` that ends script execution. The exception's only purpose
 * is to allow the system's `exception` event listeners to determine if an
 * uncaught exception was of the standard variety or the result of a fatal
 * runtime error.
 * 
 * This exception should never be thrown manually as it will make the system
 * think a fatal runtime error has occured. If purposeful script termination
 * mimicking normal shutdown is desired, the `ScriptHaltException` should be
 * used instead.
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class FatalErrorException extends RuntimeException {}
