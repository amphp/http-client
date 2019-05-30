# http-client

[![Build Status](https://img.shields.io/travis/amphp/http-client/master.svg?style=flat-square)](https://travis-ci.org/amphp/http-client)
[![CoverageStatus](https://img.shields.io/coveralls/amphp/http-client/master.svg?style=flat-square)](https://coveralls.io/github/amphp/http-client?branch=master)
![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

This package provides an asynchronous HTTP client for PHP based on [Amp](https://github.com/amphp/amp). Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

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
 - Implement an HTTP/1.1 client built on socket streams with no `libcurl` dependency

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

Prior to version 4.0, this package was named [`amphp/artax`](https://gitub.com/amphp/artax). While older tags also exist in this repository, `amphp/artax` should be used as package name for versions prior to 4.0. 

| Version                                               | Bug Fixes Until              | Security Fixes Until         |
| ----------------------------------------------------- | ---------------------------- | ---------------------------- |
| 4.x                                                   | Under development            | Under development            |
| [3.x](https://github.com/amphp/artax/tree/master)     | Supported, no end date, yet. | Supported, no end date, yet. |
| [2.x](https://github.com/amphp/artax/tree/2.x)        | Unmaintained.	               | Unmaintained.	              |
| [1.x](https://github.com/amphp/artax/tree/1.x)        | Unmaintained.                | Unmaintained.                |

## Security

If you discover any security related issues, please email [`me@kelunik.com`](mailto:me@kelunik.com) instead of using the issue tracker.

## License

The MIT License (MIT). Please see [`LICENSE`](./LICENSE) for more information.
