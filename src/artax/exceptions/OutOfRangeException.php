<?php

/**
 * Artax OutOfRangeException File
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
   * This is the same as OutOfBoundsException, but this should be used for
   * normal arrays which are indexed by number, not by key.
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class OutOfRangeException extends \OutOfBoundsException implements Exception
  {
  }
  
}
