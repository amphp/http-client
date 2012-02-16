<?php

/**
 * Artax RuntimeException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {

  /**
   * Exception thrown for errors that are only detectable at runtime
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class RuntimeException extends \RuntimeException implements Exception
  {
  }
  
}
