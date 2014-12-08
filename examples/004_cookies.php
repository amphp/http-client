<?php

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client
    $client = new Amp\Artax\Client;

    // Enable verbose sends so we can see our raw request messages in the console
    // as they're sent to the server.
    $client->setOption(Amp\Artax\Client::OP_VERBOSITY, Amp\Artax\Client::VERBOSE_SEND);

    // This request will receive and store google's Set-Cookie headers.
    $promise = $client->request('http://www.google.com/');
    $response = \Amp\wait($promise);

    // And this request will send the cookie we received in the first request.
    // In your console you'll see that this second request contains a Cookie header.
    $promise = $client->request('http://www.google.com/');
    $response = \Amp\wait($promise);

} catch (Amp\Artax\ClientException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
