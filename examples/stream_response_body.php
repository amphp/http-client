<?php

/**
 * Artax stores all responses as temp streams during retrieval before buffering them upon completion.
 * If you're retrieving large resources (or many smaller resources in parallel) it's useful to avoid
 * buffering all this data in memory at one time. By setting the "bufferBody" option to FALSE Artax
 * will return the body in its stream form without buffering it as a string.
 */

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;
$client->setOption('bufferBody', FALSE);

try {

    $response = $client->request('http://www.google.com');
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    $body = $response->getBody();
    var_dump(stream_get_contents($body));
    
} catch (Artax\ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    // Client::request() is the only Artax retrieval method that can throw. The others work in
    // parallel and instead notify error callbacks.
    
    echo $e;
}

