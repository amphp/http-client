<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Interceptor\IfOrigin;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Rfc7230;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () use ($argv) {
    try {
        $client = HttpClientBuilder::buildDefault();

        /** @var Response $response */
        $response = yield $client->request(new Request($argv[1] ?? 'https://httpbin.org/gzip'));

        \printf(
            "%s %s\r\n",
            $response->getRequest()->getMethod(),
            $response->getRequest()->getUri()
        );

        print Rfc7230::formatHeaders($response->getRequest()->getHeaders()) . "\r\n\r\n";

        \printf(
            "HTTP/%s %d %s\r\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason()
        );

        print Rfc7230::formatHeaders($response->getHeaders()) . "\r\n\r\n";

        $body = yield $response->getBody()->buffer();
        print \strlen($body) . " bytes received.\r\n";
    } catch (HttpException $error) {
        echo $error;
    }
});
