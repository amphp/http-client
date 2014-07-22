<?php // 001_get_request.php

require __DIR__ . '/../vendor/autoload.php';

$client = new Artax\BlockingClient;

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
