<?php

/**
 * Artax LengthException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception thrown when the length of something is too long or too short
   * 
   * For example, this might be thrown if a file name length is too long.
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class LengthException extends \LengthException implements Exception
  {
  }
  
}
