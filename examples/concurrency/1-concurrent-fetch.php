<?php declare(strict_types=1);

use Amp\Future;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use function Amp\async;

require __DIR__ . '/../.helper/functions.php';

$uris = [
    "https://google.com/",
    "https://github.com/",
    "https://stackoverflow.com/",
];

// Instantiate the HTTP client
$client = HttpClientBuilder::buildDefault();

$requestHandler = static function (string $uri) use ($client): string {
    $response = $client->request(new Request($uri));
    return $response->getBody()->buffer();
};

try {
    $futures = [];

    foreach ($uris as $uri) {
        $futures[$uri] = async(fn () => $requestHandler($uri));
    }

    $bodies = Future\await($futures);

    foreach ($bodies as $uri => $body) {
        print $uri . " - " . strlen($body) . " bytes" . PHP_EOL;
    }
} catch (HttpException $error) {
    echo $error;
}
