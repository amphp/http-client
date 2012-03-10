<?php

/**
 * Artax FatalErrorException File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Exceptions
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Exceptions;

/**
 * Internal exception used when a fatal E_ERROR terminates script execution
 * 
 * The internal Termination handler generates this exception in the event of
 * a fatal `E_ERROR` that ends script execution. The exception's only purpose
 * is to allow the system's `exception` event listeners to determine if an
 * uncaught exception was of the standard variety or the result of a fatal
 * runtime error.
 * 
 * @category   Artax
 * @package    Exceptions
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class FatalErrorException extends \RuntimeException
{
}
