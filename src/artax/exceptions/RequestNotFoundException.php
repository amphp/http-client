<?php
  
/**
 * Artax RequestNotFoundException File
 * 
 * PHP version 5.3
 * 
 * @category Artax
 * @package  Exceptions
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\exceptions {
  
  /**
   * Exception thrown purposefully to indicate the specified request was not found
   * 
   * @category Artax
   * @package  Exceptions
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  class RequestNotFoundException extends \Exception implements Exception
  {
  }
  
}
