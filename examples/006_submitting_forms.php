<?php

require __DIR__ . '/../vendor/autoload.php';

// Instantiate the HTTP client
$client = new Amp\Artax\Client;

$body = (new Amp\Artax\FormBody)
    ->addField('field1', 'my value')
    ->addFile('file1', __DIR__ . '/support/lorem.txt')
    ->addFile('file2', __DIR__ . '/support/answer.txt')
;

$request = (new Amp\Artax\Request)
    ->setUri('http://httpbin.org/post')
    ->setMethod('POST')
    ->setBody($body)
;

try {
    $promise = $client->request($request);
    $response = \Amp\wait($promise);

    printf(
        "HTTP/%s %d %s\n------- RESPONSE BODY -------\n%s",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason(),
        $response->getBody()
    );
} catch (Amp\Artax\ClientException $e) {
    echo $e;
}
