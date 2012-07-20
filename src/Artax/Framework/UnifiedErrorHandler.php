<?php
/**
 * UnifiedErrorHandler Class File
 *
 * @category    Artax
 * @package     Framework
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework;

use Exception,
    ErrorException,
    Artax\Events\Mediator,
    Artax\Http\Response,
    Artax\Http\StatusCodes;

/**
 * Provides unified error, exception and shutdown handling
 * 
 * @category    Artax
 * @package     Framework
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class UnifiedErrorHandler {

    /**
     * @var Response
     */
    private $response;
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @var int
     */
    private $debugMode;
    
    /**
     * Helper flag for working around https://bugs.php.net/bug.php?id=60909
     * @var bool
     */
    private $shutdownRoutineInvoked = false;
    
    /**
     * @param Response $response
     * @param Mediator $mediator
     * @param int $debugMode
     */
    public function __construct(Response $response, Mediator $mediator, $debugMode) {
        $this->response = $response;
        $this->mediator = $mediator;
        $this->debugMode = $debugMode;
    }
    
    /**
     * @return void
     */
    public function register() {
        set_error_handler(array($this, 'error'));
        set_exception_handler(array($this, 'exception'));
        register_shutdown_function(array($this, 'shutdown'));
    }
    
    /**
     * @param int    $errNo   The PHP error constant
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * @return void
     */
    public function error($errNo, $errStr, $errFile, $errLine) {
        $msg = "$errStr in $errFile on line $errLine";
        $e = new ErrorException($msg, $errNo);
        
        try {
            if (!$this->mediator->notify('__sys.error', $e, $this->debugMode)) {
                throw $e;
            }
        } catch (Exception $e) {
            
            // Because of the bug listed at  https://bugs.php.net/bug.php?id=60909
            // we manually call the exception/shutdown routine when error handling
            // results in an exception and end execution
            $this->exception($e);
            $this->shutdown();
            
            throw new ScriptHaltException;
        }
    }
    
    /**
     * @param Exception $e
     * @return void
     */
    public function exception(Exception $e) {
        if ($e instanceof ScriptHaltException) {
            return;
        }
        
        try {
            if (!$this->mediator->notify('__sys.exception', $e, $this->debugMode)) {
                $this->outputDefaultHandlerMsg($e);
            }
        } catch (ScriptHaltException $e) {
            return;
        } catch (Exception $e) {
            $this->outputDefaultHandlerMsg($e);
        }
    }
    
    /**
     * @param Exception $e
     * @return string
     */
    protected function outputDefaultHandlerMsg(Exception $e) {
        if ($this->response->wasSent()) {
            return;
        }
        
        $this->response->setStatusCode(StatusCodes::HTTP_INTERNAL_SERVER_ERROR);
        $this->response->setStatusDescription(StatusCodes::HTTP_500);
        
        if ($this->debugMode) {
            $body = '<p style="color:red;font-weight:bold;">DEBUG MODE<br />';
            $body .= 'turn off ARTAX_DEBUG_MODE to display user-friendly output on errors.</p>';
            $body .= "<pre>$e</pre>";
        } else {
            $body = '<p>Well this is embarrassing ...</p>';
        }
        
        $this->response->setBody($body);
        $this->response->setRawHeader('Content-Length: ' . strlen($body));
        $this->response->send();
    }
    
    /**
     * Handle unexpected fatal errors and notify listeners of shutdown
     * 
     * @return void
     * @notifies shutdown()
     */
    public function shutdown() {
        // Because of the bug listed at https://bugs.php.net/bug.php?id=60909 this function
        // may have already been invoked by the error handler
        if ($this->shutdownRoutineInvoked) {
            return;
        }
        
        $this->shutdownRoutineInvoked = true;
        
        if ($e = $this->buildExceptionFromFatal()) {
            $this->handleFatalError($e);
        } else {
            try {
                $this->mediator->notify('__sys.shutdown');
            } catch (ScriptHaltException $e) {
                return;
            } catch (Exception $e) {
                $this->outputDefaultHandlerMsg($e);
            }
        }
    }
    
    /**
     * @param FatalErrorException $e
     */
    private function handleFatalError(FatalErrorException $e) {
        try {
            if (!$this->mediator->notify('__sys.exception', $e, $this->debugMode)) {
                $this->outputDefaultHandlerMsg($e);
            }
            $this->mediator->notify('__sys.shutdown');
        } catch (ScriptHaltException $e) {
            return;
        } catch (Exception $e) {
            $this->outputDefaultHandlerMsg($e);
        }
    }

    /**
     * @return FatalErrorException Returns null if the most recent PHP error wasn't fatal
     */
    private function buildExceptionFromFatal() {
        $ex  = null;
        $err = $this->getLastError();
        
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
            $ex  = new FatalErrorException($msg);
        }
        
        return $ex;
    }
    
    /**
     * @return array Returns an associative error representation array
     */
    private function getLastError() {
        return error_get_last();
    }
}
