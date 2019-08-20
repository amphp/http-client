<?php

use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\Client;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () {
    try {
        // Instantiate the HTTP client
        $client = new Client;

        // Here we create a custom request object instead of simply passing an URL to request().
        // We set the method to POST and add a FormBody to submit a form.
        $body = new FormBody;
        $body->addField("search", "foobar");
        $body->addField("submit", "ok");
        $body->addFile("foo", __DIR__ . "/small-file.txt");

        $request = new Request('https://httpbin.org/post', 'POST');
        $request->setBody($body);

        // Make an asynchronous HTTP request
        $promise = $client->request($request);

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        /** @var Response $response */
        $response = yield $promise;

        // Output the results
        \printf(
            "HTTP/%s %d %s\n\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );

        // The response body is an instance of Payload, which allows buffering or streaming by the consumers choice.
        $body = yield $response->getBody()->buffer();
        print $body . "\n";
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The Client::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
