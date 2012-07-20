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
    Artax\Http\StatusCodes,
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
     * Constructor
     * 
     * @param Mediator $mediator
     * @param Request $request
     * @param Response $response
     * 
     * @return void
     */
    public function __construct(Mediator $mediator, Request $request, Response $response) {
        $this->mediator = $mediator;
        $this->request  = $request;
        $this->response = $response;
    }
    
    /**
     * Listens for the Artax `exception` event to output 500 errors
     * 
     * @param Exception $e
     * @param int $debugLevel
     * 
     * @return void
     */
    public function __invoke(Exception $e, $debugLevel) {
        $this->response->setStatusCode(StatusCodes::HTTP_NOT_ACCEPTABLE);
        $this->response->setStatusDescription('Internal Server Error');
        
        $userEvent = 'app.http-' . StatusCodes::HTTP_NOT_ACCEPTABLE;
        
        if (!$this->mediator->notify($userEvent, $this->request, $this->response, $e, $debugLevel)) {
            $body  = '<h1>500 Internal Server Error</h1><hr />' . PHP_EOL . '<p>' . PHP_EOL;
            $body .= $debugLevel ? (string) $e : 'Well this is embarrassing ...';
            $body .= '</p>';
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
