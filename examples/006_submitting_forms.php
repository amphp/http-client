<?php // 006_submitting_forms.php

//require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

$client = new Artax\BlockingClient;

$body = new Artax\FormBody;
$body->addField('field1', 'my value');
$body->addFileField('file1', __DIR__ . '/support/lorem.txt');
$body->addFileField('file2', __DIR__ . '/support/answer.txt');

$request = new Artax\Request;
$request->setUri('http://httpbin.org/post');
$request->setMethod('POST');
$request->setBody($body);

try {
    $response = $client->request($request);

    printf(
        "HTTP/%s %d %s\n------- RESPONSE BODY -------\n%s",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason(),
        $response->getBody()
    );

} catch (Artax\ClientException $e) {
    echo $e;
}
