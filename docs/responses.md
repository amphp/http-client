---
title: Responses
permalink: /responses
---
`HttpClient::request()` returns a `Promise` that resolves to an instance of `Response` as soon as the response headers are successfully received.

{:.note}
> `Response` objects are mutable (instead of immutable as in Artax v3 / PSR-7)

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
 * `getHeader(string)` returns the first header with the given name or `null` if no such header is present.
 * `getHeaderArray(string)` returns all headers with the given name, possibly an empty array.
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

`getBody()` returns a [`Payload`](https://amphp.org/byte-stream/payload), which allows simple buffering and streaming access.

{:.warning}
> `chunk = yield $response->getBody()->read();` reads only a single chunk from the body while `$contents = yield $response->getBody()->buffer()` buffers the complete body.
> Please refer to the [`Payload` documentation](https://amphp.org/byte-stream/payload) for more information.

## Request, Original Request and Previous Response

`getRequest()` allows access to the request corresponding to the response. This might not be the original request in case of redirects. `getOriginalRequest()` returns the original request sent by the client. This might not be the same request that was passed to `Client::request()`, because the client might normalize headers or assign cookies. `getPreviousResponse` allows access to previous responses in case of redirects, but the response bodies of these responses won't be available, as they're discarded. If you need access to these, you need to disable auto redirects and implement them yourself.
