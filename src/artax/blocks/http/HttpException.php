<?php
  
/**
 * Artax HttpException File
 * 
 * PHP version 5.3
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\http {
  
  /**
   * Exception thrown when an operational HTTP error occurs
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class HttpException extends \Exception implements \artax\exceptions\Exception
  {
  }
}
