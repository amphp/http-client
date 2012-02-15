<?php

/**
 * Artax LengthException File
 * 
 * PHP version 5.3
 * 
 * @category Artax
 * @package  Exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception thrown when the length of something is too long or too short
   * 
   * For example, this might be thrown if a file name length is too long.
   * 
   * @category Artax
   * @package  Exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class LengthException extends \LengthException implements Exception
  {
  }
  
}
