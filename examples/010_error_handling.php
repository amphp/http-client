<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * A non-200 response DOES NOT represent an error. A response promise returned from
 * Amp\Artax\Client::request() will only resolve as a failure if something goes seriously wrong with
 * the request/response cycle:
 *
 *     - infinite redirect loop
 *     - invalid/unparsable HTTP response message received from server
 *     - DNS resolution failure
 *     - premature loss of socket connection
 *     - malformed request URI
 *     - etc.
 *
 * All successful responses are modeled as an Amp\Artax\Response and this result is used to resolve
 * the promise result. Status codes are accessible via the standard Amp\Artax\Response::getStatus()
 * method. These should be consulted to determine the success or failure of completed responses.
 */

$badUri = "this isn't even a real URI!";
$client = new Amp\Artax\Client;

// Yielding a promise that fails will result in an exception
// being thrown back into your generator.
Amp\run(function() use ($client, $badUri) {
    try {
        $response = (yield $client->request($badUri));
    } catch (Exception $e) {
        echo $e->getMessage(), "\n";
    }
});

// Synchronously waiting on a promise that fails will throw.
try {
    $response = Amp\wait($client->request($badUri));
} catch (Exception $e) {
    echo $e->getMessage(), "\n";
}

// Amp\Promise::when() will never throw; errors are passed to
// the error-first callback.
Amp\run(function() use ($client, $badUri) {
    $client->request($badUri)->when(function($error, $result) {
        assert($error instanceof Exception);
        assert(is_null($result));
        echo $error->getMessage(), "\n";
    });
});
