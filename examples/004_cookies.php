<?php

use function Amp\Promise\wait;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client
    $client = new Amp\Artax\Client;

    // This request will receive and store google's Set-Cookie headers.
    $promise = $client->request('http://www.google.com/');
    $response = wait($promise);

    // And this request will send the cookie we received in the first request.
    $promise = $client->request('http://www.google.com/');
    $response = wait($promise);
} catch (Amp\Artax\HttpException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
