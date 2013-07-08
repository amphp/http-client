<?php

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;

$onResponse = function($requestKey, Artax\Response $r) use ($client) {
    // Cancel all the other requests when we receive the first response.
    // Because all the other requests are cancelled control will be handed
    // back from Artax\Client::requestMulti() as soon as this callback finishes.
    if ($r->getStatus() === 200) {
        $startLine = 'HTTP/' . $r->getProtocol() . ' ' . $r->getStatus() . ' ' . $r->getReason();
        echo "First response ({$requestKey}): {$startLine}\n";
        $client->cancelAll();
    }
};

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
