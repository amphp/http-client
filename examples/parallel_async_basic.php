<?php

use Amp\ReactorFactory,
    Artax\AsyncClient,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$reactor = (new ReactorFactory)->select();
$client = new AsyncClient($reactor);

$aToZ = range('a', 'z');
$requests = array_map(function($letter) { return 'http://www.bing.com/search?q=' . $letter; }, $aToZ);
$unfinishedCount = count($requests);

// Schedule our requests to fire when the reactor starts
$reactor->once(function() use ($reactor, $client, $requests, &$unfinishedCount) {
    foreach ($requests as $uri) {
        
        $onResponse = function($r) use ($uri, &$unfinishedCount, $reactor) {
            echo "{$uri} -- ", $r->getStatus(), ' ', $r->getReason(), "\n";
            
            if (!--$unfinishedCount) {
                $reactor->stop();
            }
        };
        
        $onError = function($e) use ($uri, &$unfinishedCount, $reactor) {
            echo "{$uri} Failed :(\n", $e, "\n";
            
            if (!--$unfinishedCount) {
                $reactor->stop();
            }
        };
        
        $client->request($uri, $onResponse, $onError);
    }
});

// The reactor IS our task scheduler. Nothing will happen until the reactor is started.
$reactor->run();
