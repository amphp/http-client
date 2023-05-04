<?php declare(strict_types=1);

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

require __DIR__ . '/../.helper/functions.php';

try {
    // Instantiate the HTTP client
    $client = HttpClientBuilder::buildDefault();

    // Here we create a custom request object instead of simply passing an URL to request().
    // We set the method to POST now and add a request body.
    $request = new Request('https://httpbin.org/post', 'POST');
    $request->setBody('woot \o/');

    // Make an asynchronous HTTP request
    $response = $client->request($request);

    dumpRequestTrace($response->getRequest());
    dumpResponseTrace($response);

    dumpResponseBodyPreview($response->getBody()->buffer());
} catch (HttpException $error) {
    echo $error;
}
