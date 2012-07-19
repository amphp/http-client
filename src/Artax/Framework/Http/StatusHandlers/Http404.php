<?php

/**
 * Http404 Handler Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @package      Http
 * @subpackage   StatusHandlers
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Http\StatusHandlers;

use Artax\Events\Mediator,
    Artax\Http\Request,
    Artax\Http\Response,
    Artax\Http\Exceptions\HttpStatusException;

/**
 * A default handler for 404 scenarios
 * 
 * @category     Artax
 * @package      Http
 * @subpackage   StatusHandlers
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class Http404 {
    
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
     * @param HttpStatusException $e
     * @return void
     */
    public function __invoke(HttpStatusException $e) {
        
        $this->response->setStatusCode(404);
        $this->response->setStatusDescription('Not Found');
        
        if (!$this->mediator->notify('app.http-404', $this->request, $this->response, $e)) {
            $body  = '<h1>404 Not Found</h1>' . PHP_EOL . '<hr />' . PHP_EOL;
            $body .= '<p>The requested resource could not be found:<br />' . PHP_EOL;
            $body .= '<em>'.$this->request->getUri().'</em></p>' . PHP_EOL;
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
