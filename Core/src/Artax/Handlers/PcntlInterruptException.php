<?php

/**
 * Artax PcntlInterruptException File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Handlers;

/**
 * Exception thrown when termination is requested via PCNTL
 * 
 * @category   Artax
 * @package    Core
 * @subpackage Handlers
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class PcntlInterruptException extends \RuntimeException
{
}
