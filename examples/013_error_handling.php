<?php // 013_error_handling.php

require __DIR__ . '/../vendor/autoload.php';

$client = new Artax\BlockingClient;

/**
 * Unlike some clients, a non-200 response DOES NOT represent an error. The only time an instance of
 * BlockingClient can throw is if there is some unrecoverable error with the transfer. Such
 * conditions include things like:
 * 
 *     - infinite redirect loop
 *     - invalid/unparsable HTTP response message received from server
 *     - premature loss of socket connection
 *     - etc.
 * 
 * All other responses are treated the same. Status codes are accessible via the standard
 * Artax\Response::getStatus() method. These should be consulted to determine the success or
 * failure of completed responses.
 * 
 * Calls to Artax\BlockingClient::request() should always be wrapped in a try\catch block to
 * prevent unexpected failure conditions from crashing your scripts.
 */

try {
    $response = $client->request('http://httpbin.org/user-agent');

    printf(
        "\nHTTP/%s %d %s\n\n------- RESPONSE BODY -------\n%s\n\n",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason(),
        $response->getBody()
    );

} catch (Artax\ClientException $e) {
    echo $e;
}
