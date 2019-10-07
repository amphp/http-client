---
title: Interceptors
permalink: /interceptors
---
The HTTP client allows modifying HTTP requests / responses and short circuiting via interceptors.
An example of such a short circuit is the [cache implementation](https://github.com/amphp/http-client-cache), that avoids forwarding the requests to further interceptors if it can satisfy a request completely from the cache.

```php
<?php

use Amp\Http\Client\Client;
use Amp\Http\Client\Interceptor\SetRequestHeader;

$client = new Client;
$client = $client->withApplicationInterceptor(new SetRequestHeader('x-foo', 'bar'));

$response = yield $client->request("https://httpbin.org/get");

$body = yield $response->getBody()->buffer();
```

There are two kinds of interceptors with two separate interfaces named `ApplicationInterceptor` and `NetworkInterceptor`.

## Choosing the right interceptor

Most interceptors should be implemented as `ApplicationInterceptor`. However, there's sometimes the need to have access to the underlying connection properties.
In such a case, a `NetworkInterceptor` can be implemented to access the used IPs and TLS settings.

Another use-case for implementing a `NetworkInterceptor` is an interceptor, that should only ever run if the request is sent over the network instead of served from a cache or similar. However, that should usually be solved with the configuration order of the application interceptors.

The big disadvantage of network interceptors is that they have to be rather quick and can't take too long, because they're only invoked after the connection has been created and the client will run into a timeout if there's no activity within a reasonable time.

## List of Interceptors

 - `AddRequestHeader`
 - `AddResponseHeader`
 - `DecompressResponse`
 - `FollowRedirects`
 - `ModifyRequest`
 - `ModifyResponse`
 - `RemoveRequestHeader`
 - `RemoveResponseHeader`
 - `SetRequestHeader`
 - `SetResponseHeader`
 - `SetRequestHeaderIfUnset`
 - `SetResponseHeaderIfUnset`
 - [`CookieHandler`](https://github.com/amphp/http-client-cookies)
 - [`PrivateCache`](https://github.com/amphp/http-client-cache)
