<?php
  
/**
 * Artax ScriptHaltException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception thrown to purposefully end script execution
   * 
   * This is very helpful for testability purposes. In events where you might
   * consider using `die()` or `exit()` to end script execution, this exception
   * allows you to use an exception instead. The builtin error handling
   * functions ignore this exception type and its use allows for testing "die"
   * functionality using try/catch blocks.
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ScriptHaltException extends \Exception implements Exception
  {
  }
}
