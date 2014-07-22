<?php // 003_post_request.php

//require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

$client = new Artax\BlockingClient;

try {
    $request = new Artax\Request;
    $request->setMethod('POST');
    $request->setUri('http://httpbin.org/post');
    $request->setBody('zanzibar!');

    $response = $client->request($request);

    printf(
        "\nHTTP/%s %d %s\n\n------- RESPONSE BODY -------\n%s\n",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason(),
        $response->getBody()
    );

} catch (Artax\ClientException $e) {
    echo $e;
}
