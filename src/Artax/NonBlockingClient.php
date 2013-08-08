<?php

namespace Artax;

interface NonBlockingClient extends ObservableClient {
    
    /**
     * Asynchronously request an HTTP resource
     * 
     * @param mixed $uriOrRequest An HTTP URI string or Artax\Request instance
     * @param callable $onResponse A callback to receive the Artax\Response object on completion
     * @param callable $onError A callback to receive an exception object on retrieval failure
     */
    function request($uriOrRequest, callable $onResponse, callable $onError);
    
}
