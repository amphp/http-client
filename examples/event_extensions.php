<?php

/**
 * Clients emit several events to which subscribers may listen:
 * 
 * Client::DATA         [$request, $socketDataRead]
 * Client::SEND         [$request, $socketDataSent]
 * Client::SOCKET       [$request, NULL]
 * Client::CANCEL       [$request, NULL]
 * Client::REQUEST      [$request, NULL]
 * Client::HEADERS      [$request, $parsedResponseArray]
 * Client::REDIRECT     [$request, NULL]
 * Client::RESPONSE     [$request, $response]
 * 
 * The data parameter for event broadcasts is always a two-element array. The first element is ALWAYS
 * the request responsible for the event. The second element is data pertinent to the event.
 * 
 * The example below demonstrates the ability to manually set a Response for a given request by
 * attaching to the Client::REQUEST event. The primary use-case for such an action would be to
 * examine the request and potentially serve a locally cached version.
 * 
 * Another useful action on Client::REQUEST notifications is modifying the request to add cookie
 * headers pulled from a local cookie storage.
 */

use Artax\Client,
    Artax\ClientException,
    Artax\Response;

require dirname(__DIR__) . '/autoload.php';


$client = new Client;
$subscription = $client->subscribe([
    Client::REQUEST => function($eventArr) use ($client) {
        $request = array_shift($eventArr);
        $response = (new Response)->setStatus(200)->setReason('Because We Can')->setBody('ZANZIBAR!');
        $client->setResponse($request, $response);
    }
]);

$uri = "http://www.google.com/";
$response = $client->request($uri);
echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
echo $response->getBody(), "\n";
