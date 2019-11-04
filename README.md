# http-client

[![Build Status](https://img.shields.io/travis/amphp/http-client/master.svg?style=flat-square)](https://travis-ci.org/amphp/http-client)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/http-client/master.svg?style=flat-square)](https://coveralls.io/github/amphp/http-client?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

This package provides an asynchronous HTTP client for PHP based on [Amp](https://github.com/amphp/amp). Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Features

 - Supports HTTP/1 and HTTP/2
 - [Requests concurrently by default](./examples/6-concurrent-requests.php)
 - [Pools persistent connections (keep-alive @ HTTP/1.1, multiplexing @ HTTP/2)](./examples/10-connection-pooling.php)
 - Transparently follows redirects
 - [Decodes compressed entity bodies (gzip, deflate)](./examples/12-gzip.php)
 - [Exposes headers and message data](./examples/1-get-request.php)
 - [Streams entity bodies for memory management with large transfers](./examples/5-streaming-responses.php)
 - Supports all standard and custom HTTP method verbs
 - [Simplifies HTTP form submissions](./examples/4-forms.php)
 - [Implements secure-by-default TLS (`https://`)](./examples/1-get-request.php)
 - [Supports cookies and sessions](https://github.com/amphp/http-client-cookies)

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/http-client
```

## Documentation

Documentation is bundled within this repository in the [`docs`](./docs) directory.

## Examples

More extensive code examples reside in the [`examples`](./examples) directory.

## Versioning

`amphp/http-client` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

##### 4.x

Under development.

##### [3.x](https://github.com/amphp/artax/tree/master)

Use [`amphp/artax`](https://github.com/amphp/artax) as package name instead.

##### [2.x](https://github.com/amphp/artax/tree/2.x)

No longer maintained. Use [`amphp/artax`](https://github.com/amphp/artax) as package name instead.

##### [1.x](https://github.com/amphp/artax/tree/1.x)

No longer maintained. Use [`amphp/artax`](https://github.com/amphp/artax) as package name instead.

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
