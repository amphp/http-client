<?php

require __DIR__ . '/../vendor/autoload.php';

$body = (new Artax\FormBody)
    ->addField('field1', 'my value')
    ->addFileField('file1', __DIR__ . '/support/lorem.txt')
    ->addFileField('file2', __DIR__ . '/support/answer.txt')
;

$request = (new Artax\Request)
    ->setUri('http://httpbin.org/post')
    ->setMethod('POST')
    ->setBody($body)
;

try {
    $response = (new Artax\Client)->request($request)->wait();

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
