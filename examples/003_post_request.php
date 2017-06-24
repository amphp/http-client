<?php

use Amp\Artax\BasicClient;
use function Amp\Promise\wait;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client
    $client = new BasicClient;
    $client->setOption(BasicClient::OP_VERBOSITY, BasicClient::VERBOSE_ALL);

    // Let's build up a custom Request object
    $request = (new Amp\Artax\Request('http://httpbin.org/post', "POST"))
        ->withBody('zanzibar!');

    // Make an asynchronous HTTP request
    $promise = $client->request($request);

    // Client::request() is asynchronous! It doesn't return a response. Instead, it
    // returns a promise to resolve the response at some point in the future when
    // it's finished. Here we use the Amp concurrency framework to synchronously wait
    // for the eventual promise result.
    $response = wait($promise);

    // Output the results
    printf(
        "\nHTTP/%s %d %s\n\n------- RESPONSE BODY -------\n%s\n",
        $response->getProtocolVersion(),
        $response->getStatus(),
        $response->getReason(),
        $response->getBody()
    );
} catch (Amp\Artax\HttpException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
