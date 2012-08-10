<?php

namespace WebApp\Resources;

use Artax\Http\FormEncodableRequest as Request,
    Artax\Framework\Http\ObservableResponse as Response,
    Artax\Events\Mediator,
    Artax\Framework\Events\SystemEventDeltaException;


class IllegalSysEventDelta {
    
    private $request;
    private $response;
    private $mediator;
    
    public function __construct(Request $request, Response $response, Mediator $mediator) {
        $this->request = $request;
        $this->response = $response;
        $this->mediator = $mediator;
    }
    
    public function get() {
        try {
            $this->mediator->push('__sys.exception', function(){});
        } catch (SystemEventDeltaException $e) {
            $this->response->setStatusCode(500);
            $this->response->setStatusDescription('Internal Server Error');
            $this->response->setBody('illegal sysevent delta');
            
            $this->response->send();
        }
    }

}
