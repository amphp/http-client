---
title: Interceptors
permalink: /interceptors
---
Interceptors allow customizing the `HttpClient` behavior in a composable fashion without writing another client implementation.

Interceptor use cases range from adding / removing headers from a request / response and recording timing information to more advanced use cases like a fully compliant [HTTP cache](https://github.com/amphp/http-client-cache) that intercepts requests and serves them from the cache if possible.

```php
<?php

use Amp\Http\Client\Client;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Interceptor\SetResponseHeader;
use Amp\Http\Client\Request;

$client = (new HttpClientBuilder)
    ->intercept(new SetRequestHeader('x-foo', 'bar'))
    ->intercept(new SetResponseHeader('x-tea', 'now'))
    ->build();

$response = yield $client->request(new Request("https://httpbin.org/get"));

$body = yield $response->getBody()->buffer();
```

There are two kinds of interceptors with separate interfaces named `ApplicationInterceptor` and `NetworkInterceptor`.

## Choosing the right interceptor

Most interceptors should be implemented as `ApplicationInterceptor`. However, there's sometimes the need to have access to the underlying connection properties.
In such a case, a `NetworkInterceptor` can be implemented to access the used IPs and TLS settings.

Another use-case for implementing a `NetworkInterceptor` is an interceptor, that should only ever run if the request is sent over the network instead of served from a cache or similar. However, that should usually be solved with the configuration order of the application interceptors.

The big disadvantage of network interceptors is that they have to be rather quick and can't take too long, because they're only invoked after the connection has been created and the client will run into a timeout if there's no activity within a reasonable time.

## List of Interceptors

 - `AddRequestHeader`
 - `AddResponseHeader`
 - `ConditionalInterceptor`
 - `DecompressResponse`
 - `FollowRedirects`
 - `ForbidUriUserInfo`
 - `IfOrigin`
 - `ModifyRequest`
 - `ModifyResponse`
 - `RemoveRequestHeader`
 - `RemoveResponseHeader`
 - `RetryRequests`
 - `SetRequestHeader`
 - `SetRequestHeaderIfUnset`
 - `SetResponseHeader`
 - `SetResponseHeaderIfUnset`
 - [`CookieHandler`](https://github.com/amphp/http-client-cookies)
 - [`PrivateCache`](https://github.com/amphp/http-client-cache)
