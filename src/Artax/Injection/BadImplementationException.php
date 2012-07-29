<?php
/**
 * BadImplementationException Class File
 * 
 * @category    Artax
 * @package     Injection
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Injection;

use LogicException;

/**
 * Exception thrown when an implementation doesn't subclass its specified interface/abstract
 * 
 * @category    Artax
 * @package     Injection
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class BadImplementationException extends LogicException {}
