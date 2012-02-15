<?php

/**
 * Artax RuntimeException File
 * 
 * PHP version 5.3
 * 
 * @category Artax
 * @package  Exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {

  /**
   * Exception thrown for errors that are only detectable at runtime
   * 
   * @category Artax
   * @package  Exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class RuntimeException extends \RuntimeException implements Exception
  {
  }
  
}
