<?php

use Artax\Client,
    Artax\Request,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;
$client->setOption('verboseSend', TRUE); // <-- will write the raw request to STDOUT as it's sent

$uri = 'http://httpbin.org/put'; // <-- returns the contents of our PUT request
        
$body = 'my PUT data!';
$request = (new Request)->setUri($uri)->setMethod('PUT')->setBody($body);

try {
    echo "\n------------------- START RAW REQUEST MESSAGE ------------------\n";
    $response = $client->request($request);
    $responseBody = $response->getBody();
    $decodedBody = json_decode($responseBody);
    assert($decodedBody->data === $body); // my PUT data!
    echo "\n-------------------- END RAW REQUEST MESSAGE -------------------\n";
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}
