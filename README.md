<h1 align="center"><img src="https://raw.githubusercontent.com/amphp/logo/master/repos/http-client.png?v=05-11-2019" alt="HTTP Client" width="350"></h1>

This package provides an asynchronous HTTP client for PHP based on [Amp](https://github.com/amphp/amp). Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Features

 - Supports HTTP/1 and HTTP/2
 - [Requests concurrently by default](examples/concurrency/1-concurrent-fetch.php)
 - [Pools persistent connections (keep-alive @ HTTP/1.1, multiplexing @ HTTP/2)](examples/pooling/1-connection-count.php)
 - [Transparently follows redirects](https://amphp.org/http-client/follow-redirects)
 - [Decodes compressed entity bodies (gzip, deflate)](examples/basic/7-gzip.php)
 - [Exposes headers and message data](examples/basic/1-get-request.php)
 - [Streams entity bodies for memory management with large transfers](examples/streaming/1-large-response.php)
 - [Supports all standard and custom HTTP method verbs](https://amphp.org/http-client/requests#request-method)
 - [Simplifies HTTP form submissions](examples/basic/4-forms.php)
 - [Implements secure-by-default TLS (`https://`)](examples/basic/1-get-request.php)
 - [Supports cookies and sessions](https://github.com/amphp/http-client-cookies)
 - [Functions seamlessly behind HTTP proxies](https://github.com/amphp/http-tunnel)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-client
```

Additionally, you might want to install the `nghttp2` library to take advantage of FFI to speed up and reduce the memory usage on PHP 7.4.

## Documentation

Documentation is bundled within this repository in the [`docs`](./docs) directory.

## Examples

More extensive code examples reside in the [`examples`](./examples) directory.

## Versioning

`amphp/http-client` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

Everything in an `Internal` namespace or marked as `@internal` is not public API and therefore not covered by BC guarantees.

##### 4.x

Stable and recommended version.

##### [3.x](https://github.com/amphp/artax/tree/master)

Legacy version. Use [`amphp/artax`](https://github.com/amphp/artax) as package name instead.

##### [2.x](https://github.com/amphp/artax/tree/2.x)

No longer maintained. Use [`amphp/artax`](https://github.com/amphp/artax) as package name instead.

##### [1.x](https://github.com/amphp/artax/tree/1.x)

No longer maintained. Use [`amphp/artax`](https://github.com/amphp/artax) as package name instead.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
