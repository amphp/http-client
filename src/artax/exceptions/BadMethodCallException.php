<?php

/**
 * Artax BadMethodCallException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {

  /**
   * Exception thrown when a class method call was illegal
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class BadMethodCallException extends \BadMethodCallException implements Exception
  {
  }
  
}
