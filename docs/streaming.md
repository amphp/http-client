---
title: Streaming
permalink: /streaming
---
Artax allows streaming the response body by using the [`Payload`](http://amphp.org/byte-stream/payload) API of [`amphp/byte-stream`](http://amphp.org/byte-stream).

```php
$response = yield $client->request("https://httpbin.org/get");

$body = $response->getBody();
while (null !== $chunk = yield $body->read()) {
    print $chunk;
}
```
