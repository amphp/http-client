<?php

use Artax\Client,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;
$uri = "http://www.google.com/";

try {
    $response = $client->request($uri);
    
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
    foreach ($response->getAllHeaders() as $field => $valueArray) {
        foreach ($valueArray as $value) {
            echo $field, ': ', $value, "\n";
        }
    }
    
    echo "\n";
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}
