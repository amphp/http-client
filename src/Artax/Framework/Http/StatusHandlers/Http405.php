<?php

/**
 * Http405 Handler Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @package      Http
 * @subpackage   StatusHandlers
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax\Http\StatusHandlers;

use Artax\Events\Mediator,
    Artax\Http\Request,
    Artax\Http\Response,
    Artax\Http\Exceptions\MethodNotAllowedException;

/**
 * A default handler for 405 scenarios
 * 
 * @category     Artax
 * @package      Http
 * @subpackage   StatusHandlers
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class Http405 {
    
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
     * Builds and outputs a generic 405 response
     * 
     * @return void
     */
    public function __invoke(MethodNotAllowedException $e) {
        $this->response->setStatusCode(405);
        $this->response->setStatusDescription('Method Not Allowed');
        
        // As per http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.7 ...
        // An Allow header field MUST be present in a 405 (Method Not Allowed) response
        $this->response->setHeader('Allow',
            strtoupper(implode(', ', $e->getAvailableResourceMethods()))
        );
        
        if (!$this->mediator->notify('app.http-405', $this->request, $this->response, $e)) {
            $body  = '<h1>405 Method Not Allowed</h1>' . PHP_EOL . '<hr />' . PHP_EOL;
            $body .= '<p>Request Method: <em>'.$this->request->getMethod().'</em></p>';
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
