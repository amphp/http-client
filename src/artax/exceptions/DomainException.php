<?php

/**
 * Artax DomainException File
 * 
 * PHP version 5.3
 * 
 * @category artax
 * @package  exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception that denotes a value not in the valid domain was used
   * 
   * Basically, this is what you would throw if your code messes up and for
   * example a sanity-check finds a value is "outside the domain.
   * For example, if you have a method which performs weekday calculations,
   * and for some reason a result of a calculation is outside the 1-7 range
   * (for days in a week), you could throw a DomainException. This is because
   * the value is outside the "domain" for day numbers in a week.
   * 
   * @category artax
   * @package  exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class DomainException extends \DomainException implements Exception
  {
  }
  
}
