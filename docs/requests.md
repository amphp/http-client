---
title: Requests
permalink: /requests
---
Artax allows either passing a string (which is interpreted as URI) or a `Request` object to `Client::request()`. The `Request` class can be used to specify further specifics of the request such as setting headers or changing the request method.

`Request` objects are immutable, all mutators return new instances.

## Request URI

The constructor requires an absolute request URI. `Request::withUri(string $uri)` allows changing the request method.

```php
$request = (new Request("https://httpbin.org/post", "POST"))
    ->withBody("foobar");
    
$request = $request->withUri("https://google.com/");
```

`Request::getUri()` exposes the request URI of the given `Request` object.

## Request Method

The constructor accepts an optional request method, it defaults to `GET`. `Request::withMethod(string $method)` allows changing the request method.

```php
$request = (new Request("https://httpbin.org/post", "POST"))
    ->withBody("foobar");
    
$request = $request->withMethod("PUT");
```

`Request::getMethod()` exposes the request method of the given `Request` object.

## Request Headers

`Request::withHeader(string $field, string $value)` allows changing the request headers. It will remove any previous values for that field. `Request::withAddedHeader(string $field, string $value)` allows adding an additional header line without removing existing lines.
 
`Request::withHeaders(array $headers)` allows adding multiple headers at once with the array keys being the field names and the values being the header values. The header values can also be arrays of strings to set multiple header lines.

`Request::hasHeader(string $field)` checks whether at least one header line with the given name exists.

`Request::getHeader(string $field)` returns the first header line with the given name or `null` if no such header exists.

`Request::getHeaderArray(string $field)` returns an array of header lines with the given name. An empty array is returned if no header with the given name exists.

`Request::getHeaders()` returns an associative array with the keys being the header names and the values being arrays of header lines.

```php
$request = (new Request("https://httpbin.org/post", "POST"))
    ->withHeader("X-Foobar", "Hello World")
    ->withBody("foobar");
```

## Request Bodies

`Request::withBody($body)` allows changing the request body. Accepted types are `string`, `null`, and `RequestBody`. `string` and `null` are automatically converted to an instance of `RequestBody`.

{:.note}
> `RequestBody` is basically a factory for request bodies. We cannot simply accept streams there, because a request body might have to be sent again on a redirect. Additionally, `RequestBody` allows the body to set headers, which can be used to automatically set headers such as `Content-Type: application/json` for a `JsonBody`. Note that headers set via `RequestBody::getHeaders()` are only applied if the `Request` doesn't have such a header. This allows overriding the default body header in a request. 

```php
$request = (new Request("https://httpbin.org/post", "POST"))
    ->withBody("foobar");
```

`Request::getBody()` exposes the request body of the given `Request` object and will always return a `RequestBody`.
