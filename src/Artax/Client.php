<?php

namespace Artax;

use Amp\ReactorFactory,
    Artax\Parsing\ParserFactory;

class Client implements ObservableClient {
    
    const USER_AGENT = AsyncClient::USER_AGENT;
    
    private $reactor;
    private $asyncClient;
    private $isComplete = FALSE;
    
    function __construct(AsyncClient $ac = NULL, ParserFactory $opf = NULL, SocketFactory $sf = NULL) {
        $this->reactor = (new ReactorFactory)->select();
        $this->asyncClient = $ac ?: new AsyncClient($this->reactor, $opf, $sf);
    }

    /**
     * @param $uriOrRequest
     * @return Response
     */
    function request($uriOrRequest) {
        $onResult = function(Response $response) { $this->onResult($response); };
        $onError = function(\Exception $error) { $this->onError($error); };
        
        $this->reactor->once(function() use ($onResult, $onError, $uriOrRequest) {
            $this->asyncClient->request($uriOrRequest, $onResult, $onError);
        });
        
        $this->reactor->run();
        
        $response = $this->response;
        $this->response = NULL;
        
        return $response;
    }
    
    private function onResult(Response $response) {
        $this->reactor->stop();
        $this->response = $response;
    }
    
    private function onError(\Exception $e) {
        $this->reactor->stop();
        throw $e;
    }
    
    function setResponse(Request $request, Response $response) {
        return $this->asyncClient->setResponse($request, $response);
    }
    
    function setOption($option, $value) {
        return $this->asyncClient->setOption($option, $value);
    }
    
    function setAllOptions(array $options) {
        return $this->asyncClient->setAllOptions($options);
    }
    
    function subscribe(array $eventListenerMap, $unsubscribeOnError = TRUE) {
        $this->asyncClient->subscribe($eventListenerMap, $unsubscribeOnError);
    }
    
    function unsubscribe(Subscription $subscription) {
        $this->asyncClient->unsubscribe($subscription);
    }
    
    function unsubscribeAll() {
        $this->asyncClient->unsubscribeAll();
    }
    
    function notify($event, $data = NULL) {
        $this->asyncClient->notify($event, $data);
    }
}

