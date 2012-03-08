<?php

/**
 * Artax BucketAbstract Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    blocks
 * @subpackage http
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Http {

  /**
   * BucketAbstract Class
   * 
   * @category   Artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  abstract class BucketAbstract extends \Artax\Bucket implements BucketInterface
  {
    /**
     * Constructor initializes params array
     * 
     * @param array $superglobal An associative array of superglobal values
     * 
     * @return void
     * @throws Artax\Exceptions\InvalidArgumentException On empty or non-string `$id`
     *                                                   param key
     */
    public function __construct(array $superglobal=NULL)
    {
      $this->detect($superglobal);
    }
  }
}
