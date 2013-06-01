<?php

use Artax\Client,
    Artax\Request,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

$uri = 'http://httpbin.org/post'; // <-- returns the contents of our POST request
        
$body = 'zanzibar!';
$request = (new Request)->setUri($uri)->setMethod('POST')->setBody($body);

try {
    $response = $client->request($request);
    $responseBody = $response->getBody();
    $decodedBody = json_decode($responseBody);
    
    assert($decodedBody->data === $body); // zanzibar!
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
    var_dump($decodedBody->data);
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}
