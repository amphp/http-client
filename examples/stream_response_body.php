<?php

/**
 * Artax stores all responses as temp streams during retrieval before buffering them upon completion.
 * If you're retrieving large resources (or many smaller resources in parallel) it's useful to avoid
 * buffering all this data in memory at one time. By setting the "bufferBody" option to FALSE Artax
 * will return the body in its stream form and not buffer it as a string.
 */

use Artax\Client,
    Artax\Request,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;
$client->setOption('bufferBody', FALSE);

$uri = 'http://www.google.com';
$request = (new Request)->setUri($uri)->setMethod('GET');

try {
    $response = $client->request($request);
    $responseBody = $response->getBody();
    
    assert(is_resource($responseBody));
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
    echo "\n-------------------- PARTIAL ENTITY BODY BELOW -------------------\n";
    $bufferedBody = stream_get_contents($responseBody);
    var_dump(substr($bufferedBody, 0, 350));
    echo "\n--------------------- END PARTIAL ENTITY BODY --------------------\n";
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}
