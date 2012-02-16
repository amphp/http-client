<?php

/**
 * Artax ErrorException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception thrown when a PHP error is triggered
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ErrorException extends \ErrorException implements Exception
  {
  }
}
