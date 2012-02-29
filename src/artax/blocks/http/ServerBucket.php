<?php

/**
 * Artax HTTP Package ServerBucket Class File
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
   * HTTP ServerBucket Class
   * 
   * The class does not perform any processing on the SERVER values; it simply
   * stores them in a key=>value container.
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ServerBucket extends BucketAbstract
  {
    /**
     * Auto-detect SERVER vars and populate associated bucket params
     * 
     * @param array $_server An optional SERVER value array
     * 
     * @return Object instance for method chaining
     */
    public function detect($_server=NULL) {
      $this->clear();
      $_server = $_server ? $_server : $_SERVER;
      $this->load($_server);
      return $this;
    }
  }
}
