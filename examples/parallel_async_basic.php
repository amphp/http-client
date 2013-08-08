<?php

/**
 * The paradigm of event-driven parallelism is beyond the scope of this example but the main
 * takeaway for our purposes is that everything takes place inside a non-blocking event loop.
 * `Artax\AsyncClient` relies on the Amp library's event reactor to function. This example
 * demonstrates how to schedule and execute a set of parallel requests in the context of an
 * event loop. You'll notice that the individual responses return in a different order from that
 * in which they were originally requested.
 */

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$reactor = (new Alert\ReactorFactory)->select();
$client = new Artax\AsyncClient($reactor);

// Generate a request URI for each letter a-z
$requests = array_map(function($alpha) { return 'http://www.bing.com/search?q=' . $alpha; }, range('a', 'z'));

// We need to track how many requests remain so we can stop the program when they're all finished
$unfinishedRequests = count($requests);

// What to do when an individual request completes
$onResponse = function(Artax\Response $response, Artax\Request $request) use (&$unfinishedRequests, $reactor) {
    echo $request->getUri(), ' -- ';
    echo 'HTTP/', $response->getProtocol(), ' ', $response->getStatus(), ' ', $response->getReason(), "\n";
    if (!--$unfinishedRequests) {
        $reactor->stop();
    }
};

// What to do if a request encounters an exceptional error
$onError = function(Exception $e, Artax\Request $request) use (&$unfinishedRequests, $reactor) {
    echo $request->getUri(), " failed (", get_class($e), ") :(\n";
    if (!--$unfinishedRequests) {
        $reactor->stop();
    }
};

// Schedule this to happen as soon as the reactor starts
$reactor->once(function() use ($client, $requests, $onResponse, $onError) {
    echo 'Requesting ', count($requests), ' URIs ...', "\n";
    foreach ($requests as $uri) {
        $client->request($uri, $onResponse, $onError);
    }
});

// The reactor IS our task scheduler and the program runs inside it. Nothing will happen until the
// event reactor is started, so release the hounds!
$reactor->run();
