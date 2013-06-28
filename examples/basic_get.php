<?php

/**
 * Often we only care about simple GET retrieval. For such cases Artax accepts a simple URI as the
 * request parameter.
 */

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;

try {
    $response = $client->request('http://httpbin.org/user-agent');

    echo "Response status code: ", $response->getStatus(), "\n";
    echo "Response reason:      ", $response->getReason(), "\n";
    echo "Response protocol:    ", $response->getProtocol(), "\n";
    
    print_r($response->getAllHeaders());
    
    echo $response->getBody(), "\n";
    
} catch (Artax\ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    // Client::request() is the only Artax retrieval method that can throw. The others work in
    // parallel and instead notify error callbacks.
    
    echo $e;
}

