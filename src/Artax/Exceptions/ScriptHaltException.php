<?php

/**
 * Artax ScriptHaltException File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage exceptions
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Exceptions {

  /**
   * Exception purposefully thrown to end script execution
   * 
   * This exception makes testing code much simpler. Instead of calling `die`
   * or `exit` directly, methods can throw a `ScriptHaltException` which is
   * an easily testable outcome. The built-in Artax FatalHandler will exit
   * quietly in the event of an uncaught `ScriptHaltException`.
   * 
   * @category   Artax
   * @package    core
   * @subpackage exceptions
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ScriptHaltException extends \RuntimeException
  {
  }  
}
