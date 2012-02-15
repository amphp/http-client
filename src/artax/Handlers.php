<?php

/**
 * Artax Handler Class File
 *
 * PHP version 5.4
 *
 * @category Artax
 * @package  Core
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
  class Handlers implements HandlersInterface
  {
    use UsesConfigTrait;
    
    /**
     * Initializes the Artax exception/shutdown handler methods
     * 
     * @return void
     */
    public function __construct(Config $config=NULL)
    {
      $this->config = $config;
      
      set_exception_handler([$this, 'exHandler']);
      register_shutdown_function([$this, 'shutdown']);
    }
    
    /**
     * The "last chance" handler for uncaught exceptions
     *
     * @param \Exception $e Exception object
     *
     * @return void
     * @see Handlers::unexpectedError
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
     * What to do if a requested resource could not be found
     *
     * @return void
     * @throws exceptions\ScriptHaltException Ends script execution
     */
    protected function notFound()
    {
      if ($this->config && $this->config->exists('custom404Handler')) {
        $f = $this->config->get('custom404Handler');
        $f();
      } else {
        echo  PHP_EOL . '404 NOT FOUND' . PHP_EOL . PHP_EOL;
      }
    }

    /**
     * Handle undexpected internal errors
     *
     * @param \Exception $e Exception
     * @param Request    $r Artax Request object
     *
     * @return void;
     */
    protected function unexpectedError(\Exception $e=NULL)
    {
      if ($this->config && $this->config->exists('custom500Handler')) {
        $f = $this->config->get('custom500Handler');
        $f();
      } else {
        echo PHP_EOL;
        echo $e->getMessage() .' in '. $e->getFile() .' on line '. $e->getLine();
        echo PHP_EOL;
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
