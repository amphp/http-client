<?php

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client
    $client = new Amp\Artax\Client;

    // Let's build up a custom Request object
    $request = (new Amp\Artax\Request)
        ->setMethod('GET') // <-- defaults to GET if not assigned, so this isn't strictly necessary
        ->setUri('http://httpbin.org/user-agent')
        ->setHeader('X-My-Header', 'some-value')
    ;

    // Make an asynchronous HTTP request
    $promise = $client->request($request);

    // Client::request() is asynchronous! It doesn't return a response. Instead, it
    // returns a promise to resolve the response at some point in the future when
    // it's finished. Here we use the Amp concurrency framework to synchronously wait
    // for the eventual promise result.
    $response = \Amp\wait($promise);

    // Output the results
    printf(
        "\nHTTP/%s %d %s\n",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );

} catch (Amp\Artax\ClientException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
