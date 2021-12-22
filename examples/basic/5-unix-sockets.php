<?php

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Socket\StaticSocketConnector;
use function Amp\Socket\socketConnector;

require __DIR__ . '/../.helper/functions.php';

try {
    // Unix sockets require a socket pool that changes all URLs to a fixed one.
    $connector = new StaticSocketConnector("unix:///var/run/docker.sock", socketConnector());

    $client = (new HttpClientBuilder)
        ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)))
        ->build();

    // amphp/http-client requires a host, so just use a dummy one.
    $request = new Request('http://docker/info');

    // Make an asynchronous HTTP request
    $response = $client->request($request);

    dumpRequestTrace($response->getRequest());
    dumpResponseTrace($response);

    dumpResponseBodyPreview($response->getBody()->buffer());
} catch (HttpException $error) {
    // If something goes wrong Amp will throw the exception where the promise was yielded.
    // The HttpClient::request() method itself will never throw directly, but returns a promise.
    echo $error;
}
