---
title: Concurrent Requests
permalink: /concurrent
description: HTTP client allows making multiple requests concurrently. It leverages non-blocking I/O like all other Amp libraries for that.
---
The HTTP client allows making multiple requests concurrently. It leverages non-blocking I/O like all other Amp libraries for that.
Instead of sending one request, waiting for the response, then doing something different like sending another request, we can use the time we're usually waiting for the server to respond to send a second request to another (or the same) server.

{:.table-no-border .table-full-width .table-text-center}
| ![sequential requests]({{ site.baseurl }}/images/requests-sequential.svg)<br>*Sequential Requests* | ![concurrent requests]({{ site.baseurl }}/images/requests-parallel.svg)<br>*Concurrent Requests* |

As you can see in the sequence diagrams, we save some time there. We only have to wait for the maximum response delay of both requests instead of the sum of both. With more requests this speedup is even more interesting.

```php
<?php

$client = Amp\Http\Client\HttpClientBuilder::buildDefault();
$promises = [];

$urls = ['https://github.com/', 'https://google.com/', 'https://amphp.org/http-client'];
foreach ($urls as $url) {
    $promises[$url] = Amp\call(static function () use ($client, $url) {
        $request = new Request($url);
        // "yield" inside a coroutine awaits the resolution of the promise
        // returned from Client::request(). The generator is then continued.
        $response = yield $client->request($request);

        // Same for the body here.
        $body = yield $response->getBody()->buffer();

        return $body;
    });
}

$responses = Amp\Promise\wait(Amp\Promise\all($promises));
```

If you need to perform a large set of requests, have a look at [concurrent iterators](https://amphp.org/sync/concurrent-iterator).
