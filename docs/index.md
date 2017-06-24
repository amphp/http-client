---
title: Artax
permalink: /
---
Artax is an asynchronous HTTP/1.1 client for [Amp](https://github.com/amphp/amp). Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Installation

```php
composer require amphp/amp
```

## Usage

The main interaction point with this library is `BasicClient`. It implements all basic HTTP features and some more advanced ones such as cookies. It implements the `Client` interface that can be used to implement other clients, such as a wrapper that provides caching.

### Basic HTTP Request

In its simplest form, Artax takes an URL as string and interprets that as a `GET` request to that resource without any custom headers. Standard headers like `Accept`, `Connection` or `Host` will automatically be added.

```php
$client = new Amp\Artax\BasicClient;

$response = yield $client->request("https://httpbin.org/get");

var_dump($response->getStatus());
var_dump($response->getAllHeaders());
var_dump(yield $response->getBody());
```

### Custom Requests

It's also possible to pass a `Request` object instead of a string to `Client::request()`.

```php
$request = (new Request("https://httpbin.org/post", "POST"))
    ->withBody("foobar");
    
$response = yield $client->request($request);
```

### Streaming Responses

Artax allows streaming the response body by using the [`Message`](http://amphp.org/byte-stream/message) API of [`amphp/byte-stream`](http://amphp.org/byte-stream).

```php
$response = yield $client->request("https://httpbin.org/get");

$body = $response->getBody();
while (null !== $chunk = yield $body->read()) {
    print $chunk;
}
```
