<?php

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Interceptor\LogHttpArchive;
use Amp\Http\Client\Interceptor\MatchOrigin;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/../.helper/functions.php';

Loop::run(static function () use ($argv) {
    try {
        $client = (new HttpClientBuilder)
            ->intercept(new LogHttpArchive(__DIR__ . '/log.har'))
            ->intercept(new MatchOrigin(['https://amphp.org' => new SetRequestHeader('x-amphp', 'true')]))
            ->followRedirects(0)
            ->retry(3)
            ->build();

        for ($i = 0; $i < 5; $i++) {
            /** @var Response $response */
            $response = yield $client->request(new Request($argv[1] ?? 'https://httpbin.org/user-agent'));

            dumpRequestTrace($response->getRequest());
            dumpResponseTrace($response);

            dumpResponseBodyPreview(yield $response->getBody()->buffer());
        }
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The HttpClient::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
