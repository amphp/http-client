<?php

/**
 * Artax OutOfBoundsException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {

  /**
   * Exception thrown when an illegal index was requested
   * 
   * An OutOfBoundsException should be thrown if code is attempting to
   * access an invalid key. Typically this could be used in code that
   * attempts to access an associative array, but performs a check for the key.
   * 
   * Note: This should be used for keys, not indexes (as in strings, not numbers).
   * You may wish to check when implementing whether or not the offset being
   * read is a number or not, and throw an OutOfRangeException instead.
   * 
   * @category artax
  * @package   exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class OutOfBoundsException extends \OutOfBoundsException implements Exception
  {
  }
  
}
