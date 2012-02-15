<?php

/**
 * Artax BucketInterface Interface File
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
   * BucketInterface Interface
   * 
   * @category   artax
   * @package    blocks
   * @subpackage http
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface BucketInterface
  {
    /**
     * Auto-detect bucket params from PHP superglobal arrays
     * 
     * @return void
     */
    public function detect();
  }
}
