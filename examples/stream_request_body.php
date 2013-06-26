<?php

use Artax\Client,
    Artax\Request,
    Artax\FileBody,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

$path = __DIR__ . '/support/stream_body.txt';
$body = new FileBody($path);

$request = (new Request)->setUri('http://httpbin.org/post')->setMethod('POST')->setBody($body);

try {
    $response = $client->request($request);
    $responseBody = $response->getBody();
    $decodedBody = json_decode($responseBody);
    
    assert($decodedBody->data === file_get_contents($path));
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
    echo "\n-------------------- REQUEST ENTITY BODY BELOW -------------------\n";
    echo $decodedBody->data;
    echo "\n--------------------- END REQUEST ENTITY BODY --------------------\n";
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}
