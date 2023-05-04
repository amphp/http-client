<?php declare(strict_types=1);

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

require __DIR__ . '/../.helper/functions.php';

try {
    // Instantiate the HTTP client, nothing special to do, GZIP is supported out of the box.
    $client = HttpClientBuilder::buildDefault();

    $response = $client->request(new Request($argv[1] ?? 'https://httpbin.org/gzip'));

    dumpRequestTrace($response->getRequest());
    dumpResponseTrace($response);

    dumpResponseBodyPreview($response->getBody()->buffer());
} catch (HttpException $error) {
    echo $error;
}
