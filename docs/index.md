---
title: An Asynchronous HTTP Client for PHP
permalink: /
---
Artax is an asynchronous HTTP/1.1 client for PHP based on Amp. Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Installation

```php
composer require amphp/artax
```

## Usage

The main interaction point with this library is `DefaultClient`. It implements all basic HTTP features and some more advanced ones such as cookies. It implements the `Client` interface that can be used to implement other clients, such as a wrapper that provides caching. Consumers should always declare the `Client` interface as a type.

### Basic HTTP Request

In its simplest form, Artax takes an URL as string and interprets that as a `GET` request to that resource without any custom headers. Standard headers like `Accept`, `Connection` or `Host` will automatically be added if not present.

```php
$client = new Amp\Artax\DefaultClient;

$response = yield $client->request("https://httpbin.org/get");

var_dump($response->getStatus());
var_dump($response->getHeaders());

// Response::getBody() returns a Message
// See http://amphp.org/byte-stream/message
var_dump(yield $response->getBody());
```
