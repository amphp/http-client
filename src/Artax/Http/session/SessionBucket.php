<?php

/**
 * Artax SessionBucket Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    blocks
 * @subpackage session
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\blocks\session {

  /**
   * SessionBucket Class
   * 
   * @category   Artax
   * @package    blocks
   * @subpackage session
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class SessionBucket extends \Artax\Bucket
  {
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
      $this->startSession();
      $this->params =& $_SESSION;
    }
    
    /**
     * Starts session if it doesn't yet exist
     * 
     * @return void
     */
    protected function startSession()
    {
      if ( ! $this->sessionExists()) {
        session_start();
      }
    }
    
    /**
     * Determine if a session has been started
     * 
     * @return bool Returns TRUE if the session has been started or FALSE if not.
     */
    protected function sessionExists()
    {
      return session_id() == '' ? FALSE : TRUE;
    }
  }
}
