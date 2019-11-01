---
title: An Asynchronous HTTP Client for PHP
permalink: /
---
`amphp/http-client` is an asynchronous HTTP/1.1 and HTTP/2 client for PHP based on Amp. Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Features

 - Supports HTTP/1 and HTTP/2
 - Requests concurrently by default
 - Pools persistent connections (keep-alive @ HTTP/1.1, multiplexing @ HTTP/2)
 - Transparently follows redirects
 - Decodes compressed entity bodies (gzip, deflate)
 - Exposes headers and message data
 - Streams entity bodies for memory management with large transfers
 - Supports all standard and custom HTTP method verbs
 - Simplifies HTTP form submissions
 - Implements secure-by-default TLS (`https://`)
 - Supports cookies and sessions

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```php
composer require amphp/http-client
```

## Usage

The main interaction point with this library is the `HttpClient` interface.
`HttpClient` instances can be built using `HttpClientBuilder` without knowing about the exiting implementations of the `HttpClient` interface.

`HttpClientBuilder` allows to register two kinds of [interceptors](./interceptors.md), which allows customizing the `HttpClient` behavior in a composable fashion without writing another client implementation.

### Basic Example

In its simplest form, the HTTP client takes a request with an URL as string and interprets that as a `GET` request to that resource without any custom headers. Standard headers like `Accept`, `Connection` or `Host` will automatically be added if not present.

```php
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;

$client = HttpClientBuilder::buildDefault();

$response = yield $client->request(new Request("https://httpbin.org/get"));

var_dump($response->getStatus());
var_dump($response->getHeaders());
var_dump(yield $response->getBody()->buffer());
```
