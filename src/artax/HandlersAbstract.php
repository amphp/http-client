<?php

/**
 * Artax Handler Class File
 *
 * PHP version 5.4
 *
 * @category artax
 * @package  core
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
namespace artax {

  /**
   * Artax Handler Class
   *
   * Provides exception and shutdown handling functionality
   *
   * @category artax
   * @package  core
   * @author   Daniel Lowrey <rdlowrey@gmail.com>
   */
  abstract class HandlersAbstract implements HandlersInterface
  {
    /**
     * @var bool
     */
    protected $debug = FALSE;
    
    /**
     * Turn debug flag on or off
     * 
     * @param bool $val
     */
    public function setDebug($val)
    {
      $this->debug = (bool) $val;
    }
    
    /**
     * The "last chance" handler for uncaught exceptions
     *
     * @param \Exception $e Exception object
     *
     * @return void
     */
    public function exHandler(\Exception $e)
    {
      if ($e instanceof exceptions\RequestNotFoundException) {
        $this->notFound();
      } elseif ( ! $e instanceof exceptions\ScriptHaltException) {
        $this->unexpectedError($e);
      }
    }

    /**
     * Handle unexpected fatal errors
     *
     * @return void
     * @uses Handler::fatalErrorOccurred
     * @uses Handler::unexpectedError
     */
    public function shutdown()
    {
      if ($e = $this->fatalErrorOccurred()) {
        $this->unexpectedError($e);
      }
    }

    /**
     * Determine if the last triggered PHP error was fatal
     * 
     * If the last occuring error during script execution was fatal the function
     * returns a `artax\exceptions\RuntimeException` object representing the error so it
     * can be handled by the shutdown handler.
     * 
     * @return mixed bool(FALSE) if none/non-fatal error -or- RuntimeException if
     *               last error occurence was fatal.
     * @used-by Handler::shutdown
     */
    protected function fatalErrorOccurred()
    {
      if ( ! $err = error_get_last()) {
        return FALSE;
      }
      $str = "Uncaught exception 'artax\exceptions\ScriptHaltException'";
      if (strstr($err['message'], $str)) {
        return FALSE;
      }

      $fatals = array(
        E_ERROR           => 'Fatal Error',
        E_PARSE           => 'Parse Error',
        E_CORE_ERROR      => 'Core Error',
        E_CORE_WARNING    => 'Core Warning',
        E_COMPILE_ERROR   => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning'
      );
      
      if (isset($fatals[$err['type']])) {
        $msg = $fatals[$err['type']] . ': ' . $err['message'] . ' in ';
        $msg.= $err['file'] . ' on line ' . $err['line'];
        return new exceptions\RuntimeException($msg);
      }
      return FALSE;
    }
  }
}
