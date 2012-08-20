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
    protected $response;
    
    /**
     * @var Mediator
     */
    protected $mediator;
    
    /**
     * @var int
     */
    protected $debugMode;
    
    /**
     * Helper flag for working around https://bugs.php.net/bug.php?id=60909
     * @var bool
     */
    protected $shutdownRoutineInvoked = false;
    
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
        set_error_handler(array($this, 'handleError'));
        set_exception_handler(array($this, 'handleException'));
        register_shutdown_function(array($this, 'handleShutdown'));
    }
    
    /**
     * @param int    $errNo   The PHP error constant
     * @param string $errStr  The resulting PHP error message
     * @param string $errFile The file where the PHP error originated
     * @param int    $errLine The line in which the error occurred
     * @return void
     */
    public function handleError($errNo, $errStr, $errFile, $errLine) {
        if (0 === $this->getErrorReportingValue()) {
            return; // respect @ error suppression
        }
        
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
            $this->handleException($e);
            $this->handleShutdown();
            
            throw new ScriptHaltException;
        }
    }
    
    protected function getErrorReportingValue() {
        return error_reporting();
    }
    
    /**
     * @param Exception $e
     * @return void
     */
    public function handleException(Exception $e) {
        if ($e instanceof ScriptHaltException) {
            return;
        }
        
        try {
            if (!$this->mediator->notify('__sys.exception', $e, $this->debugMode)) {
                $this->outputDefaultExceptionMessage($e);
            }
        } catch (ScriptHaltException $scriptHaltException) {
            return;
        } catch (Exception $nestedException) {
            $this->outputDefaultExceptionMessage($nestedException);
        }
    }
    
    /**
     * @param Exception $e
     * @return string
     */
    protected function outputDefaultExceptionMessage(Exception $e) {
        if ($this->response->wasSent()) {
            return;
        }
        
        $this->response->setStatusCode(StatusCodes::HTTP_INTERNAL_SERVER_ERROR);
        $this->response->setStatusDescription(StatusCodes::HTTP_500);
        
        $body = '<h1>500 Internal Server Error</h1><hr />' . PHP_EOL;
        if ($this->debugMode) {
            $body .= "<pre>$e</pre>";
        }
        
        $this->response->setStatusCode(500);
        $this->response->setStatusDescription('Internal Server Error');
        $this->response->setBody($body);
        $this->response->setHeader('Content-Type', 'text/html');
        $this->response->setHeader('Content-Length', strlen($body));
        $this->response->send();
    }
    
    /**
     * @return void
     */
    public function handleShutdown() {
        // Because of the bug listed at https://bugs.php.net/bug.php?id=60909 this function
        // may have already been invoked by the error handler
        if ($this->shutdownRoutineInvoked) {
            return;
        }
        
        $this->shutdownRoutineInvoked = true;
        
        $fatalException = $this->buildExceptionFromFatalError();
        
        if ($fatalException) {
            $this->handleException($fatalException);
        }
        
        try {
            $this->mediator->notify('__sys.shutdown');
        } catch (ScriptHaltException $scriptHaltException) {
            return;
        } catch (Exception $nestedException) {
            $this->outputDefaultExceptionMessage($nestedException);
        }
    }

    /**
     * @return FatalErrorException
     */
    protected function buildExceptionFromFatalError() {
        $lastError = $this->getLastError();
        
        $fatals = array(
            E_ERROR           => 'Fatal Error',
            E_PARSE           => 'Parse Error',
            E_CORE_ERROR      => 'Core Error',
            E_CORE_WARNING    => 'Core Warning',
            E_COMPILE_ERROR   => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning'
        );
        
        if (isset($fatals[$lastError['type']])) {
            $msg = $fatals[$lastError['type']] . ': ' . $lastError['message'] . ' in ';
            $msg.= $lastError['file'] . ' on line ' . $lastError['line'];
            return new FatalErrorException($msg);
        }
    }
    
    /**
     * @return array
     */
    protected function getLastError() {
        return error_get_last();
    }
}
