<?php

use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\Http1Tunnel;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use Amp\Socket\SocketAddress;

require __DIR__ . '/../.helper/functions.php';

Loop::run(static function () use ($argv) {
    try {
        // We only support direct TCP connection to the proxy, no TLS, because TLS in TLS is not supported
        // Use a tunnel to the proxy if you need a encrypted connection to the proxy itself
        $connector = new Http1Tunnel(new SocketAddress('127.0.0.1', 5512));

        // If you need authentication, you can set a custom header (using Basic auth here)
        // $connector = new Http1Tunnel(new SocketAddress('127.0.0.1', 5512), [
        //     'proxy-authorization' => 'Basic ' . \base64_encode('user:pass'),
        // ]);

        $client = (new HttpClientBuilder)
            ->usingPool(new UnlimitedConnectionPool($connector))
            ->build();

        $request = new Request('http://amphp.org/');

        /** @var Response $response */
        $response = yield $client->request($request);

        dumpRequestTrace($response->getRequest());
        dumpResponseTrace($response);

        dumpResponseBodyPreview(yield $response->getBody()->buffer());
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The HttpClient::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
