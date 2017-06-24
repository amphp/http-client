<?php

use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    try {
        // Instantiate the HTTP client
        $client = new Amp\Artax\BasicClient;

        // Here we create a custom request object instead of simply passing an URL to request().
        $request = (new Request('http://httpbin.org/headers'))
            ->withHeader('X-Hello-World', 'Awesome \o/');

        // Make an asynchronous HTTP request
        $promise = $client->request($request);

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        /** @var Response $response */
        $response = yield $promise;

        // Output the results
        printf(
            "HTTP/%s %d %s\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );

        foreach ($response->getAllHeaders() as $field => $values) {
            foreach ($values as $value) {
                print "$field: $value\n";
            }
        }

        print "\n";

        // The response body is an instance of Message, which allows buffering or streaming by the consumers choice.
        // Simply yielding a Message buffers the complete response body.
        $body = yield $response->getBody();
        print $body . "\n";
    } catch (Amp\Artax\HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The Client::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
