---
title: An Asynchronous HTTP Client for PHP
permalink: /
---
`amphp/http-client` is an asynchronous HTTP/1.1 and HTTP/2 client for PHP based on Amp. Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Installation

```php
composer require amphp/http-client
```

## Usage

The main interaction point with this library is `Client`. [Interceptors](./interceptors.md) can be added to intercept and modify requests / responses.

### Basic HTTP Request

In its simplest form, the HTTP client takes an URL as string and interprets that as a `GET` request to that resource without any custom headers. Standard headers like `Accept`, `Connection` or `Host` will automatically be added if not present.

```php
$client = new Amp\Http\Client\Client;

$response = yield $client->request("https://httpbin.org/get");

var_dump($response->getStatus());
var_dump($response->getHeaders());
var_dump(yield $response->getBody()->buffer());
```
