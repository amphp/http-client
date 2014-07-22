<?php // 010_response_memory.php

/**
 * Artax stores all responses as temp streams during retrieval before buffering them upon completion.
 * If you're retrieving large resources (or many smaller resources in parallel) it's useful to avoid
 * buffering the full response body in memory. By setting the Client::OP_BUFFER_BODY option to FALSE
 * Artax will return the body in its stream form without buffering it first.
 */

//require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

$client = new Artax\BlockingClient;
$client->setOption(Client::OP_BUFFER_BODY, FALSE);

try {
    $response = $client->request('http://www.google.com');

    printf(
        "HTTP/%s %d %s\n",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );

    $body = $response->getBody();
    var_dump($body); // resource(%d) of type (stream)
    $bufferedBody = stream_get_contents($body);
    var_dump($bufferedBody); // string(%d) "..."

} catch (Artax\ClientException $e) {
    echo $e;
}
