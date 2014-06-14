<?php

namespace Artax;

interface BlockingClient extends ObservableClient {

    /**
     * Synchronously request an HTTP resource
     * 
     * @param string|Request $uriOrRequest An http:// or https:// URI string or \Artax\Request instance
     */
    function request($uriOrRequest);
    
    /**
     * Synchronously request multiple HTTP resources in parallel
     * 
     * @param array $requests An array of URI strings and/or Artax\Request instances
     * @param callable $onEachResponse Receives Artax\Response instance on request completion
     * @param callable $onEachError Receives an Exception instance on request completion
     */
    function requestMulti(array $requests, callable $onEachResponse, callable $onEachError);
    
}
