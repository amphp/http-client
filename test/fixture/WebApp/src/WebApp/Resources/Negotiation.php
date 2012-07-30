<?php

namespace WebApp\Resources;

use RuntimeException,
    Artax\Http\StdRequest,
    Artax\Framework\Http\ObservableResponse,
    Artax\Framework\Http\RequestNegotiator;

class Negotiation {
    
    private $request;
    private $response;
    
    public function __construct(StdRequest $request, ObservableResponse $response) {
        $this->request = $request;
        $this->response = $response;
    }
    
    /**
     * The RequestNegotiator instance could also have been typehinted in the constructor.
     * It's typehinted here to demonstrate that typehinted resource method parameters are also
     * provisioned automatically during the routing process.
     */
    public function get(RequestNegotiator $negotiator) {
        // If negotiation breaks down (the client can't accept your available 
        // content/charset/lang/encodings) a NotAcceptableException is thrown to
        // automatically initiate a 406 Not Acceptable response.
        $negotiatedVals = $this->negotiate($negotiator);
        
        $contentType = $negotiatedVals['contentType'];
        $charset     = $negotiatedVals['charset'];
        $language    = $negotiatedVals['language'];
        $encoding    = $negotiatedVals['encoding'];
        
        $body = $this->generateBody($contentType);
        
        $this->response->setBody($body);
        $this->response->send();
    }
    
    /**
     * @throws NotAcceptableException
     */
    private function negotiate(RequestNegotiator $negotiator) {
        // order your negotiation values by server preference -- if the client doesn't have a
        // preference between multiple available negotiables, the negotiator will choose the
        // first acceptable value in the array.
        $negotiator->setAvailableContentTypes(array('text/html', 'application/json'));
        $negotiator->setAvailableCharsets(array('utf-8'));
        $negotiator->setAvailableLanguages(array('en-us'));
        $negotiator->setAvailableEncodings(array('gzip', 'deflate', 'identity'));
        
        // Automatically assigns the response headers related to negotiation for you.
        // It's up to you to actually provide content in the negotiated format.
        // If you'd rather assign Response headers manually (not recommended) you can instead
        // use the `RequestNegotiator::negotiate(Request $request)` method.
        return $negotiator->negotiateAndApply($this->request, $this->response);
    }
    
    /**
     * If negotiation fails (406 Not Acceptable) an exception is thrown that automatically triggers
     * the __sys.http-406 event (customize using app.http-406 listener(s)).
     * 
     * @throws NotAcceptableException
     */
    private function generateBody($contentType) {
        switch ($contentType) {
            case 'text/html':
                return '<h1>Hello, world.</h1>';
            case 'application/json':
                return json_encode('Hello, world.');
            default:
                throw new RuntimeException(
                    'You should never see this because it means the content negotiation ' .
                    'component has a bug'
                );
        }
    }
}
