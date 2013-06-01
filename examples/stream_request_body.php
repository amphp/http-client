<?php

use Artax\Client,
    Artax\Request,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

$uri = 'http://httpbin.org/post'; // <-- returns the contents of our POST request

$bodyFile = __DIR__ . '/support/stream_body.txt';
$body = fopen($bodyFile, 'r');
$request = (new Request)->setUri($uri)->setMethod('POST')->setBody($body);

try {
    $response = $client->request($request);
    $responseBody = $response->getBody();
    $decodedBody = json_decode($responseBody);
    
    assert($decodedBody->data === file_get_contents($bodyFile));
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
    echo "\n-------------------- REQUEST ENTITY BODY BELOW -------------------\n";
    echo $decodedBody->data;
    echo "\n--------------------- END REQUEST ENTITY BODY --------------------\n";
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}
