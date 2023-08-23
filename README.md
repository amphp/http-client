# amphp/http-client

AMPHP is a collection of event-driven libraries for PHP designed with fibers and concurrency in mind.
This package provides an asynchronous HTTP client for PHP based on [Revolt](https://revolt.run/).
Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol.
The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Features

 - Supports HTTP/1 and HTTP/2
 - [Requests concurrently by default](examples/concurrency/1-concurrent-fetch.php)
 - [Pools persistent connections (keep-alive @ HTTP/1.1, multiplexing @ HTTP/2)](examples/pooling/1-connection-count.php)
 - [Transparently follows redirects](#redirects)
 - [Decodes compressed entity bodies (gzip, deflate)](examples/basic/7-gzip.php)
 - [Exposes headers and message data](examples/basic/1-get-request.php)
 - [Streams entity bodies for memory management with large transfers](examples/streaming/1-large-response.php)
 - [Supports all standard and custom HTTP method verbs](#request-method)
 - [Simplifies HTTP form submissions](examples/basic/4-forms.php)
 - [Implements secure-by-default TLS (`https://`)](examples/basic/1-get-request.php)
 - [Supports cookies and sessions](#cookies)
 - [Functions seamlessly behind HTTP proxies](#proxies)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-client
```

Additionally, you might want to install the `nghttp2` library to take advantage of FFI to speed up and reduce the memory usage.

## Usage

The main interaction point with this library is the `HttpClient` class.
`HttpClient` instances can be built using `HttpClientBuilder` without knowing about the existing implementations.

`HttpClientBuilder` allows to register two kinds of [interceptors](#interceptors), which allows customizing the `HttpClient` behavior in a composable fashion.

In its simplest form, the HTTP client takes a request with a URL as string and interprets that as a `GET` request to that resource without any custom headers.
Standard headers like `Accept`, `Connection` or `Host` will automatically be added if not present.

```php
use Amp\Http\Client\HttpClientBuilder;

$client = HttpClientBuilder::buildDefault();

$response = $client->request(new Request("https://httpbin.org/get"));

var_dump($response->getStatus());
var_dump($response->getHeaders());
var_dump($response->getBody()->buffer());
```

### Request

The `HttpClient` requires a `Request` being passed as first argument to `request()`.
The `Request` class can be used to specify further specifics of the request such as setting headers or changing the request method.

> **Note**
> `Request` objects are mutable (instead of immutable as in `amphp/artax` / PSR-7).
>
> Cloning `Request` objects will result in a deep clone, but doing so is usually only required if requests are retried or cloned for sub-requests.

#### Request URI

The constructor requires an absolute request URI. `Request::setUri(string $uri)` allows changing the request URI.

```php
$request = new Request("https://httpbin.org/post", "POST");
$request->setBody("foobar");
$request->setUri("https://google.com/");
```

`Request::getUri()` exposes the request URI of the given `Request` object.

#### Request Method

The constructor accepts an optional request method, it defaults to `GET`. `Request::setMethod(string $method)` allows changing the request method.

```php
$request = new Request("https://httpbin.org/post", "POST");
$request->setBody("foobar");
$request->setMethod("PUT");
```

`Request::getMethod()` exposes the request method of the given `Request` object.

#### Request Headers

`Request::setHeader(string $field, string $value)` allows changing the request headers. It will remove any previous values for that field. `Request::addHeader(string $field, string $value)` allows adding an additional header line without removing existing lines.

`Request::setHeaders(array $headers)` allows adding multiple headers at once with the array keys being the field names and the values being the header values. The header values can also be arrays of strings to set multiple header lines.

`Request::hasHeader(string $field)` checks whether at least one header line with the given name exists.

`Request::getHeader(string $field)` returns the first header line with the given name or `null` if no such header exists.

`Request::getHeaderArray(string $field)` returns an array of header lines with the given name. An empty array is returned if no header with the given name exists.

`Request::getHeaders()` returns an associative array with the keys being the header names and the values being arrays of header lines.

```php
$request = new Request("https://httpbin.org/post", "POST");
$request->setHeader("X-Foobar", "Hello World");
$request->setBody("foobar");
```

#### Request Bodies

`Request::setBody($body)` allows changing the request body. Accepted types are `string`, `null`, and `RequestBody`. `string` and `null` are automatically converted to an instance of `RequestBody`.

> **Note**
> `RequestBody` is basically a factory for request bodies. We cannot simply accept streams here, because a request body might have to be sent again on a redirect / retry. Additionally, `RequestBody` allows the body to set headers, which can be used to automatically set headers such as `Content-Type: application/json` for a `JsonBody`. Note that headers set via `RequestBody::getHeaders()` are only applied if the `Request` doesn't have such a header. This allows overriding the default body header in a request.

```php
$request = new Request("https://httpbin.org/post", "POST");
$request->setBody("foobar");
```

`Request::getBody()` exposes the request body of the given `Request` object and will always return a `RequestBody`.

### Response

`HttpClient::request()` returns a `Response` as soon as the response headers are successfully received.

> **Note**
> `Response` objects are mutable (instead of immutable as in Artax v3 / PSR-7)

#### Response Status

You can retrieve the response's HTTP status using `getStatus()`. It returns the status as an integer. The optional (and possibly empty) reason associated with the status can be retrieved using `getReason()`.

```php
$response = $client->request($request);

var_dump($response->getStatus(), $response->getReason());
```

#### Response Protocol Version

You can retrieve the response's HTTP protocol version using `getProtocolVersion()`.

```php
$response = $client->request($request);

var_dump($response->getProtocolVersion());
```

#### Response Headers

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

#### Response Body

`getBody()` returns a [`Payload`](https://v3.amphp.org/byte-stream#payload), which allows simple buffering and streaming access.

> **Warning**
> `$chunk = $response->getBody()->read();` reads only a single chunk from the body while `$contents = $response->getBody()->buffer()` buffers the complete body.
> Please refer to the [`Payload` documentation](https://v3.amphp.org/byte-stream#payload) for more information.

#### Request, Original Request and Previous Response

`getRequest()` allows access to the request corresponding to the response. This might not be the original request in case of redirects. `getOriginalRequest()` returns the original request sent by the client. This might not be the same request that was passed to `Client::request()`, because the client might normalize headers or assign cookies. `getPreviousResponse` allows access to previous responses in case of redirects, but the response bodies of these responses won't be available, as they're discarded. If you need access to these, you need to disable auto redirects and implement them yourself.

### Interceptors

Interceptors allow customizing the `HttpClient` behavior in a composable fashion.
Use cases range from adding / removing headers from a request / response and recording timing information to more advanced use cases like a fully compliant [HTTP cache](https://github.com/amphp/http-client-cache) that intercepts requests and serves them from the cache if possible.

```php
use Amp\Http\Client\Client;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Interceptor\SetResponseHeader;
use Amp\Http\Client\Request;

$client = (new HttpClientBuilder)
    ->intercept(new SetRequestHeader('x-foo', 'bar'))
    ->intercept(new SetResponseHeader('x-tea', 'now'))
    ->build();

$response = $client->request(new Request("https://httpbin.org/get"));
$body = $response->getBody()->buffer();
```

There are two kinds of interceptors with separate interfaces named `ApplicationInterceptor` and `NetworkInterceptor`.

#### Choosing the right interceptor

Most interceptors should be implemented as `ApplicationInterceptor`.
However, there's sometimes the need to have access to the underlying connection properties.
In such a case, a `NetworkInterceptor` can be implemented to access the used IPs and TLS settings.

Another use-case for implementing a `NetworkInterceptor` is an interceptor, that should only ever run if the request is sent over the network instead of served from a cache or similar.
However, that should usually be solved with the configuration order of the application interceptors.

The big disadvantage of network interceptors is that they have to be rather quick and can't take too long, because they're only invoked after the connection has been created and the client will run into a timeout if there's no activity within a reasonable time.

#### List of Interceptors

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

### Redirects

If you use `HttpClientBuilder`, the resulting `HttpClient` will automatically follow up to ten redirects by default.
Automatic following can be customized or disabled (using a limit of `0`) using `HttpClientBuilder::followRedirects()`.

#### Redirect Policy

The `FollowRedirects` interceptor will only follow redirects with a `GET` method.
If another request method is used and a `307` or `308` response is received, the response will be returned as is, so another interceptor or the application can take care of it.
Cross-origin redirects will be attempted without any headers set, so any application headers will be discarded.
If `HttpClientBuilder` is used to configure the client, the `FollowRedirects` interceptor is the outermost interceptor, so any headers set by interceptors will still be present in the response.
It is therefore recommended to set headers via interceptors instead of directly in the request.

#### Examining the Redirect Chain

All previous responses can be accessed from the resulting `Response` via `Response::getPreviousResponse()`.
However, the response body is discarded on redirects, so it can no longer be consumed.
If you want to consume redirect response bodies, you need to implement your own interceptor.

### Cookies

See [`amphp/http-client-cookies`](https://github.com/amphp/http-client-cookies).

### Logging

The `LogHttpArchive` event listener allows logging all requests / responses including detailed timing information to an [HTTP archive (HAR)](https://en.wikipedia.org/wiki/HAR_%28file_format%29).

These log files can then be imported into the browsers developer tools or online tools like [HTTP Archive Viewer](http://www.softwareishard.com/har/viewer/) or [Google's HAR Analyzer](https://toolbox.googleapps.com/apps/har_analyzer/).

> **Warning**
> Be careful if your log files might contain sensitive information in URLs or headers if you submit these files to third parties like the linked services above.

```php
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\EventListener\LogHttpArchive;

$httpClient = (new HttpClientBuilder)
    ->listen(new LogHttpArchive('/tmp/http-client.har'))
    ->build();

$httpClient->request(...);
```

![HAR Viewer Screenshot](https://user-images.githubusercontent.com/2743004/196048526-bf496986-ea5b-4a30-9b3f-4fa51a9c5bb1.png)

### Proxies

See [`amphp/http-tunnel`](https://github.com/amphp/http-tunnel).

## Versioning

`amphp/http-client` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

Everything in an `Internal` namespace or marked as `@internal` is not public API and therefore not covered by BC guarantees.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
