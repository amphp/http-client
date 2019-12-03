<?php

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Socket\DnsConnector;
use Amp\Socket\StaticConnector;

require __DIR__ . '/../.helper/functions.php';

Loop::run(static function () use ($argv) {
    try {
        // Unix sockets require a socket pool that changes all URLs to a fixed one.
        $connector = new StaticConnector("unix:///var/run/docker.sock", new DnsConnector);

        $client = (new HttpClientBuilder)
            ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)))
            ->build();

        // amphp/http-client requires a host, so just use a dummy one.
        $request = new Request('http://docker/info');

        // Make an asynchronous HTTP request
        $promise = $client->request($request);

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        /** @var Response $response */
        $response = yield $promise;

        dumpRequestTrace($response->getRequest());
        dumpResponseTrace($response);

        dumpResponseBodyPreview(yield $response->getBody()->buffer());
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The HttpClient::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
