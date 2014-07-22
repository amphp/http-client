<?php // 002_custom_request.php

require __DIR__ . '/../vendor/autoload.php';

$client = new Artax\BlockingClient;

try {
    $request = new Artax\Request;
    $request->setMethod('GET');
    $request->setUri('http://httpbin.org/user-agent');
    $request->setHeader('X-My-Header', 'some-value');

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
