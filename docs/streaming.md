---
title: Streaming
permalink: /streaming
---
Artax allows streaming the response body by using the [`Message`](http://amphp.org/byte-stream/message) API of [`amphp/byte-stream`](http://amphp.org/byte-stream).

```php
$response = yield $client->request("https://httpbin.org/get");

$body = $response->getBody();
while (null !== $chunk = yield $body->read()) {
    print $chunk;
}
```
