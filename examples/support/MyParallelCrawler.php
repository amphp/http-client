<?php

use Alert\Reactor,
    Artax\Uri,
    Artax\Request,
    Artax\Response,
    Artax\AsyncClient;

class MyParallelCrawler {
    
    private $reactor;
    private $client;
    private $requestQueue = [];
    private $completedUris = [];
    
    function __construct(Reactor $reactor, AsyncClient $client) {
        $this->reactor = $reactor;
        $this->client = $client;
    }
    
    /**
     * When called the crawler will start working on the specified URI. Because we don't want the
     * example to run forever we schedule an event to kill the script 5 seconds after the reactor
     * is started.
     */
    function crawl($uri) {
        $this->reactor->immediately(function() use ($uri) {
            $this->doRequest($uri);
        });
        
        $this->reactor->once(function() {
            $this->reactor->stop();
            die;
        }, $delay = 15000);
        
        $this->reactor->run();
    }
    
    private function doRequest($uri) {
        $request = (new Request)->setUri($uri);
        $onResponse = function($response) use ($request) { $this->onResponse($request, $response); };
        $onError = function($e) use ($request) { $this->onError($request, $e); };
        
        $this->client->request($request, $onResponse, $onError);
    }
    
    private function onResponse(Request $request, Response $response) {
        $uri = new Uri($request->getUri());
        
        if ($response->getStatus() == 200) {
            $body = $response->getBody();
            $this->parseLinksFromRawBody($uri, $body);
        }
        
        echo "\nCompleted $uri -- ", $response->getStatus(), ' ', $response->getReason(), "\n";
        
        $uriStr = $uri->__toString();
        $this->completedUris[$uriStr] = TRUE;
        $this->dequeueNextRequest();
    }
    
    private function onError(Request $request, \Exception $e) {
        $uri = $request->getUri();
        $this->dequeueNextRequest();
        
        echo "Error retrieving $uri\n";
    }
    
    private function parseLinksFromRawBody($baseUri, $body) {
        $dom = new DOMDocument;
        
        if (!@$dom->loadHTML($body)) {
            return;
        }
        
        foreach ($dom->getElementsByTagName('a') as $a) {
            if ($uri = $this->buildLinkUri($baseUri, $a)) {
                $this->enqueueRequest($uri);
            }
        }
    }
    
    private function buildLinkUri(Uri $baseUri, \DOMNode $a) {
        if (!$a->hasAttribute('href')) {
            $result = NULL;
        } elseif ($href = $a->getAttribute('href')) {
            $result = $this->resolve($baseUri, $href);
        } else {
            $result = NULL;
        }
        
        return $result;
    }
    
    private function resolve(Uri $baseUri, $href) {
        if (stripos($href, 'javascript:') === 0
            || $href == '#'
            || !$baseUri->canResolve($href)
        ) {
            return NULL;
        }
        
        $resolvedUri = $baseUri->resolve($href);
        $resolvedUriStr = $resolvedUri->__toString();
        
        if (isset($this->completedUris[$resolvedUriStr])) {
            return NULL;
        } else {
            echo "\tlink: $href\n";
            
            return $resolvedUri;
        }
    }
    
    private function enqueueRequest($uri) {
        if (array_push($this->requestQueue, $uri) < 10) {
            $this->dequeueNextRequest();
        }
    }
    
    private function dequeueNextRequest() {
        if ($this->requestQueue) {
            $uri = array_shift($this->requestQueue);
            $this->doRequest($uri);
        }
    }
}
