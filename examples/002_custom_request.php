<?php

use function Amp\Promise\wait;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client
    $client = new Amp\Artax\Client;

    // Let's build up a custom Request object
    $request = (new Amp\Artax\Request('http://httpbin.org/user-agent'))
        ->withHeader('X-My-Header', 'some-value');

    // Make an asynchronous HTTP request
    $promise = $client->request($request);

    // Client::request() is asynchronous! It doesn't return a response. Instead, it
    // returns a promise to resolve the response at some point in the future when
    // it's finished. Here we use the Amp concurrency framework to synchronously wait
    // for the eventual promise result.
    $response = wait($promise);

    // Output the results
    printf(
        "\nHTTP/%s %d %s\n",
        $response->getProtocolVersion(),
        $response->getStatus(),
        $response->getReason()
    );

} catch (Amp\Artax\HttpException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
