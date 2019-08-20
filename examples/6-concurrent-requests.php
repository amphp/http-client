<?php

use Amp\Http\Client\Client;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(static function () {
    $uris = [
        "https://newlifecoffee.com/",
        "https://newlifecoffee.com/coffee",
        "https://newlifecoffee.com/images/splash/summer-coolers.png",
    ];

    // Instantiate the HTTP client
    $client = new Client;

    $requestHandler = static function (string $uri) use ($client) {
        /** @var Response $response */
        $response = yield $client->request(new Request($uri));

        return yield $response->getBody()->buffer();
    };

    try {
        $promises = [];

        foreach ($uris as $uri) {
            $promises[$uri] = Amp\call($requestHandler, $uri);
        }

        $bodies = yield $promises;

        foreach ($bodies as $uri => $body) {
            print $uri . " - " . \strlen($body) . " bytes" . PHP_EOL;
        }
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The Client::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
