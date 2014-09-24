<?php

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client
    $client = new Amp\Artax\Client;
    $client->setOption(Amp\Artax\Client::OP_VERBOSITY, Amp\Artax\Client::VERBOSE_ALL);

    // Let's build up a custom Request object
    $request = (new Amp\Artax\Request)
        ->setMethod('POST')
        ->setUri('http://httpbin.org/post')
        ->setBody('zanzibar!')
    ;

    // Make an asynchronous HTTP request
    $promise = $client->request($request);

    // Client::request() is asynchronous! It doesn't return a response. Instead, it
    // returns a promise to resolve the response at some point in the future when
    // it's finished. Here we tell the promise that we want to wait for it to complete.
    $response = $promise->wait();

    // Output the results
    printf(
        "\nHTTP/%s %d %s\n\n------- RESPONSE BODY -------\n%s\n",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason(),
        $response->getBody()
    );

} catch (Amp\Artax\ClientException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
