<?php
/**
 * BadListenerException class file
 * 
 * @category    Artax
 * @package     Core
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax;

use RuntimeException;

/**
 * Exception thrown on lazy class listener instantiation or invocation failure
 * 
 * @category    Artax
 * @package     Core
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class BadListenerException extends RuntimeException {}
