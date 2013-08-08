<?php

use Alert\ReactorFactory,
    Artax\Request,
    Artax\AsyncClient,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$reactor = (new ReactorFactory)->select();
$client = new AsyncClient($reactor);

// Subscribe to CANCEL events to demonstrate in the console that our cancellations worked
$client->addObservation([
    AsyncClient::CANCEL => function($eventArr) {
        $request = current($eventArr);
        echo 'Request cancelled: ', $request->getUri(), "\n";
    }
]);


$completionCount = 0;
$aToZ = range('a', 'z');

foreach ($aToZ as $alpha) {
    $requests[$alpha] = (new Request)->setUri('http://www.bing.com/search?q=' . $alpha);
}

// Schedule the requests to be fired when the reactor starts
$reactor->once(function() use ($reactor, $client, $requests, &$completionCount) {
    foreach ($requests as $request) {
        $uri = $request->getUri();
        
        $onResponse = function($r) use ($uri, &$completionCount, $client, $reactor) {
            echo "{$uri} -- ", $r->getStatus(), ' ', $r->getReason(), "\n";
            
            // Cancel all outstanding requests after the third completed response
            if (++$completionCount >= 3) {
                $client->cancelAll();
                $reactor->stop();
            }
        };
        
        $onError = function($e) use ($uri, &$completionCount, $client, $reactor) {
            echo "{$uri} Failed :(\n", $e, "\n";
            
            // Cancel all outstanding requests after the third completed response
            if (++$completionCount >= 3) {
                $client->cancelAll();
                $reactor->stop();
            }
        };
        
        $client->request($uri, $onResponse, $onError);
    }
});

// The reactor IS our task scheduler. Nothing will happen until the reactor is started.
$reactor->run();
