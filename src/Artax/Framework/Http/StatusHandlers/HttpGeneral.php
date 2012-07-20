<?php
/**
 * HttpGeneral Handler Class File
 * 
 * @category     Artax
 * @package      Framework
 * @subpackage   Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Framework\Http\StatusHandlers;

use LogicException,
    Artax\Events\Mediator,
    Artax\Http\Request,
    Artax\Http\Response,
    Artax\Http\Exceptions\HttpStatusException;

/**
 * A catch-all listener for HttpStatusException handling
 * 
 * @category     Artax
 * @package      Framework
 * @subpackage   Http
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class HttpGeneral {
    
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
     * Notifies and outputs a response
     * 
     * @return void
     */
    public function __invoke(HttpStatusException $e) {
        $statusCode = $e->getCode();
        $this->response->setStatusCode($statusCode);
        $this->response->setStatusDescription($e->getMessage());
        
        if (!$this->mediator->notify("app.http-$statusCode", $this->request, $this->response, $e)) {
            throw new LogicException("No handlers specified for HTTP $statusCode events");
        }
        
        if (!$this->response->wasSent()) {
            $this->response->send();
        }
        
        return false;
    }
}
