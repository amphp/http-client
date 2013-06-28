<?php

/**
 * Artax eliminates the difficulty of threaded or event-looped concurrency and allows the retrieval
 * of many requests in parallel while still retaining a synchronous API. Note that though the
 * individual requests in the batch are retrieved in parallel, the `Client::requestMulti()` call
 * itself will block until all the requests complete (or error out).
 */

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;

// What to do when an individual response in the batch completes
$onResponse = function($requestKey, Artax\Response $response) {
    echo 'Response: (', $requestKey, ') ', $response->getStatus(), "\n";
};

// What to do if an individual request in the batch fails
$onError = function($requestKey, Exception $error) {
    echo 'Error: (', $requestKey, ') ', get_class($error), "\n";
};

$requests = [
    'google' => 'http://www.google.com',
    'google news' => 'http://news.google.com',
    'bing' => 'http://www.bing.com',
    'yahoo' => 'http://www.yahoo.com',
    'php' => 'http://www.php.net'
];

$client->requestMulti($requests, $onResponse, $onError);

