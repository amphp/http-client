<?php

/**
 * Artax BucketAbstract Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\http {

  /**
   * BucketAbstract Class
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  abstract class BucketAbstract extends \artax\Bucket implements BucketInterface
  {
    /**
     * Constructor initializes params array
     * 
     * @param array $superglobal An associative array of superglobal values
     * 
     * @return void
     * @throws artax\exceptions\InvalidArgumentException On empty or non-string `$id`
     *                                                   param key
     */
    public function __construct(array $superglobal=NULL)
    {
      $this->detect($superglobal);
    }
  }
}
