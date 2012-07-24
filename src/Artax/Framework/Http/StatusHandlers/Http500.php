<?php
/**
 * Http500 Handler Class File
 * 
 * @category     Artax
 * @package      Framework
 * @subpackage   Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Framework\Http\StatusHandlers;

use Exception,
    Artax\Events\Mediator,
    Artax\Http\Request,
    Artax\Http\Response;

/**
 * Outputs a DEBUG-level appropriate 500 response in the event of an uncaught exception
 * 
 * @category     Artax
 * @package      Framework
 * @subpackage   Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class Http500 {
    
    /**
     * @var Mediator
     */
    private $mediator;
    
    /**
     * @var Request
     */
    private $request;
    
    /**
     * @var Response
     */
    private $response;
    
    /**
     * @param Mediator $mediator
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function __construct(Mediator $mediator, Request $request, Response $response) {
        $this->mediator = $mediator;
        $this->request  = $request;
        $this->response = $response;
    }
    
    /**
     * @param Exception $e
     * @param int $debugMode
     * @return void
     */
    public function __invoke(Exception $e, $debugMode) {
        $this->response->setStatusCode(500);
        $this->response->setStatusDescription('Internal Server Error');
        
        if (!$this->mediator->notify(
            'app.http-500',
            $this->request,
            $this->response,
            $e,
            $debugMode
        )) {
            $body  = '<h1>500 Internal Server Error</h1><hr />' . PHP_EOL;
            
            if ($debugMode) {
                $body .= "<h2 style=\"color:red;\">DEBUG MODE</h2><pre>$e</pre>";
            } else {
                $body .= '<p>Well this is embarrassing ...</p>';
            }
            
            $this->response->setBody($body);
            $this->response->setHeader('Content-Type', 'text/html');
            $this->response->setHeader('Content-Length', strlen($body));
        }
        
        if (!$this->response->wasSent()) {
            $this->response->send();
        }
        
        return false;
    }
}
