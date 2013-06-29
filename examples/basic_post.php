<?php

/**
 * For non-trivial requests Artax allows you to construct messages piece-by-piece. This example
 * sets the request method to POST and assigns an entity body. HTTP veterans will notice that
 * we don't bother to set a Content-Length (or Transfer-Encoding: chunked) header. Aerys will
 * automatically add/normalize missing headers for us so we don't need to worry about it. The only
 * property that _MUST_ be assigned when sending an Artax\Request is the absolute http:// or
 * https:// request URI.
 */

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;
$request = (new Artax\Request)->setUri('http://httpbin.org/post')->setMethod('POST')->setBody('zanzibar!');

try {
    $response = $client->request($request);
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    echo "---------------------- RESPONSE BODY ------------------\n";
    echo $response->getBody(), "\n";
    
} catch (Artax\ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    // Client::request() is the only Artax retrieval method that can throw. The others work in
    // parallel and instead notify error callbacks.
    
    echo $e;
}
