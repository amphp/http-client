<?php

/**
 * Artax BadFunctionCallException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception thrown when a function call was illegal
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class BadFunctionCallException extends \BadFunctionCallException implements Exception
  {
  }
  
}
