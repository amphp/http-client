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
 *
 * Amp\Artax\Client::request() will NEVER throw an exception. However, calling Promise::wait() on a
 * response promise CAN throw if the promise resolves as a failure.
 */

$badUri = "this isn't even a real URI!";

// Demonstrate the lack of a thrown exception inside the event loop
Amp\run(function() use ($badUri) {
    (new Amp\Artax\Client)->request($badUri)->when(function($error, $result) {
        assert($error instanceof \Exception);
        assert(is_null($result));
        echo "See, no throw; the promise resolves as a failure using the relevant exception\n";
        Amp\stop();
    });
});

// Demonstrate that Promise::wait() can and will throw in synchronous code if a promise fails
try {
    (new Amp\Artax\Client)->request($badUri)->wait();
} catch (Exception $e) {
    echo "See, I told you Promise::wait() would throw!\n";
}
