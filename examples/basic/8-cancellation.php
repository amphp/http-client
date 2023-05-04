<?php declare(strict_types=1);

use Amp\CancelledException;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\TimeoutCancellation;

require __DIR__ . '/../.helper/functions.php';

try {
    $client = (new HttpClientBuilder)
        ->followRedirects(0)
        ->retry(3)
        ->build();

    $response = $client->request(new Request($argv[1] ?? 'https://httpbin.org/delay/5'), new TimeoutCancellation(2));

    dumpRequestTrace($response->getRequest());
    dumpResponseTrace($response);

    dumpResponseBodyPreview($response->getBody()->buffer());
} catch (HttpException | CancelledException $error) {
    echo $error;
}
