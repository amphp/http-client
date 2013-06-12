<?php

require dirname(__DIR__) . '/autoload.php';

$client = new Artax\Client;

$onResponse = function($requestKey, Artax\Response $response) {
    echo 'Response: (', $requestKey, ') ', $response->getStatus(), "\n";
};
$onError = function($requestKey, Exception $error) {
    echo 'Error: (', $requestKey, ') ', $e->getMessage(), "\n";
};
$requests = [
    'google' => 'http://www.google.com',
    'google news' => 'http://news.google.com',
    'bing' => 'http://www.bing.com',
    'yahoo' => 'http://www.yahoo.com',
    'php' => 'http://www.php.net'
];

$client->requestMulti($requests, $onResponse, $onError);
