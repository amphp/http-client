<?php

/**
 * Artax BucketInterface Interface File
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
   * BucketInterface Interface
   * 
   * @category   Artax
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
