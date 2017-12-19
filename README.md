# artax

[![Build Status](https://img.shields.io/travis/amphp/artax/master.svg?style=flat-square)](https://travis-ci.org/amphp/artax)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/artax/master.svg?style=flat-square)](https://coveralls.io/github/amphp/artax?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

Artax is an asynchronous HTTP client for PHP based on [Amp](https://github.com/amphp/amp). Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Features

 - Requests asynchronously for full single-threaded concurrency
 - Pools persistent keep-alive connections
 - Transparently follows redirects
 - Decodes gzipped entity bodies
 - Exposes headers and message data
 - Streams entity bodies for memory management with large transfers
 - Supports all standard and custom HTTP method verbs
 - Simplifies HTTP form submissions
 - Implements secure-by-default TLS (`https://`)
 - Supports cookies and sessions
 - Functions seamlessly behind HTTP proxies

## Project Goals

 - Model all code as closely as possible to the relevant HTTP protocol RFCs
 - Implement an HTTP/1.1 client built on raw socket streams with no `libcurl` dependency

## Installation

This package can be installed as a [Composer](https://getcomposer.org/) dependency.

```bash
composer require amphp/artax
```

## Documentation

Documentation is bundled within this repository in the [`docs`](./docs) directory.

## Examples

More extensive code examples reside in the [`examples`](./examples) directory.

## Versioning

`amphp/artax` follows the [semver](http://semver.org/) semantic versioning specification like all other `amphp` packages.

| Version | Bug Fixes Until | Security Fixes Until |
| ------- | --------------- | -------------------- |
| 3.x     | TBA             | TBA                  |
| 2.x     | 2017-12-31      | 2018-12-31           |
| 1.x     | Unmaintained.   | Unmaintained.        |

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
