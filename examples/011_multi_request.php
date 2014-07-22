<?php // 011_multi_request.php

require __DIR__ . '/../vendor/autoload.php';

$client = new Artax\BlockingClient;

$responses = $client->requestMulti([
    'google' => 'http://www.google.com',
    'news'   => 'http://news.google.com',
    'bing'   => 'http://www.bing.com',
]);

foreach ($responses as $requestKey => $responseStruct) {
    list($succeeded, $responseOrException) = $responseStruct;

    if ($succeeded) {
        assert($responseOrException instanceof Artax\Response);
        printf(
            "--- %s ---\nHTTP/%s %d %s\n",
            $requestKey,
            $responseOrException->getProtocol(),
            $responseOrException->getStatus(),
            $responseOrException->getReason()
        );
    } else {
        assert($responseOrException instanceof Exception);
        printf(
            "Request failed (%s):\n%s\n",
            $requestKey,
            $responseOrException->getMessage()
        );
    }
}
