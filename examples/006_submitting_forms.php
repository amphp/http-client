<?php

use function Amp\Promise\wait;

require __DIR__ . '/../vendor/autoload.php';

// Instantiate the HTTP client
$client = new Amp\Artax\Client;

$body = new Amp\Artax\FormBody;
$body->addField('field1', 'my value');
$body->addFile('file1', __DIR__ . '/support/lorem.txt');
$body->addFile('file2', __DIR__ . '/support/answer.txt');

$request = (new Amp\Artax\Request('http://httpbin.org/post', "POST"))
    ->withBody($body);

try {
    $promise = $client->request($request);
    $response = wait($promise);

    printf(
        "HTTP/%s %d %s\n------- RESPONSE BODY -------\n%s",
        $response->getProtocolVersion(),
        $response->getStatus(),
        $response->getReason(),
        wait($response->getBody())
    );
} catch (Amp\Artax\HttpException $e) {
    echo $e;
}
