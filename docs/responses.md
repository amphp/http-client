---
title: Responses
permalink: /responses
---
`Client::request()` returns a `Promise` that resolves to an instance of `Response` as soon as the response headers are successfully received.

## Response Status

You can retrieve the response's HTTP status using `getStatus()`. It returns the status as an integer. The optional (and possibly empty) reason associated with the status can be retrieved using `getReason()`.

```php
$response = yield $client->request($request);

var_dump($response->getStatus(), $response->getReason());
```

## Response Protocol Version

You can retrieve the response's HTTP protocol version using `getProtocolVersion()`.

```php
$response = yield $client->request($request);

var_dump($response->getProtocolVersion());
```

## Response Headers

Response headers can be accessed by a set of methods.

 * `hasHeader(string)` returns whether a given header is present.
 * `getHeader(string)` returns the first header line of the specified header or `null` if it doesn't exist.
 * `getHeaderArray(string)` returns all header lines, possibly an empty array.
 * `getHeaders()` returns all headers as an associative array, see below.

**`getHeaders()` Format**

```php
[
    "header-1" => [
        "value-1",
        "value-2",
    ],
    "header-2" => [
        "value-1",
    ],
]
```

## Response Body

`getBody()` returns a [`Message`](https://amphp.org/byte-stream/message), which allows simple buffering and streaming access.

{:.note}
> `chunk = yield $response->getBody()->read();` reads only a single chunk from the body while `$contents = yield $response->getBody()` buffers the complete body. Please refer to the `Message` documentation for more information.

## Request, Original Request and Previous Response

`getRequest()` allows access to the request corresponding to the response. This might not be the original request in case of redirects. `getOriginalRequest()` returns the original request sent by the client. This might not be the same request that was passed to `Client::request()`, because the client might normalize headers or assign cookies. `getPreviousResponse` allows access to previous responses in case of redirects, but the response bodies of these responses won't be available, as they're discarded. If you need access to these, you need to disable auto redirects and implement them yourself.

## Meta Data

You can access some meta data collected during the request cycle using `getMetaInfo()`. Currently there's only connection info available via `getMetaInfo()->getConnectionInfo()`. `ConnectionInfo` allows access to the local socket address, the remote socket address, and a `TlsInfo` object via `getLocalAddress()`, `getRemoteAddress()` and `getTlsInfo()`.
