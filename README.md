# Artax HTTP Client

Artax is a full-featured HTTP/1.1 client as specified in RFC 2616.  Its API is designed to simplify
standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the
underlying HTTP protocol. Checkout the [**Artax Wiki**][wiki] for full coverage of the available features:

#### FEATURES

 - Doesn't use *cURL* or *libcurl*
 - Sends and receives requests in parallel for maximum throughput
 - Transparently follows redirects
 - Provides access to all headers and raw HTTP message data
 - Automatically requests and decodes gzipped response entity bodies
 - Fully customizable (and secure by default) TLS (https://) support
 - Allows stream request/response entity bodies for hands-on memory management
 - Retains persistent connections (keep-alive) for high-performance CLI applications
 - Supports all standard and custom request methods as per the (extensible) HTTP protocol
 - Exposes a simple subject/observer API to allow custom response caching, cookie management, etc.

#### PROJECT GOALS

* Implement an HTTP/1.1 Client built on raw sockets with no libcurl dependency;
* Model all relevant code on the HTTP/1.1 protocol as outlined in [RFC 2616][rfc2616];
* Eschew the use of `static` entirely in favor of maximum testability and full API transparency;
* Build all components using [SOLID][solid], readable and 100% unit-tested code;

#### INSTALLATION

```bash
$ git clone --recursive https://github.com/rdlowrey/Artax.git
```

#### REQUIREMENTS

* PHP 5.4+
* The [Amp][amp-github] library.
* PHP's `openssl` extension if you need TLS (https://)
* PHP's `zlib` extension if you wish to request/decompress gzipped response bodies

#### WHAT'S WITH THE NAME?

Children of the 1980s are likely familiar with [The NeverEnding Story][neverending] and may remember
the scene where Atreyu's faithful steed, Artax, died in the Swamp of Sadness. The name is an homage.

[rfc2616]: http://www.w3.org/Protocols/rfc2616/rfc2616.html
[amp-github]: https://github.com/rdlowrey/Amp
[solid]: http://en.wikipedia.org/wiki/SOLID_(object-oriented_design) "S.O.L.I.D."
[neverending]: http://www.imdb.com/title/tt0088323/ "The NeverEnding Story"
[requirements]: https://github.com/rdlowrey/Artax/wiki/Requirements
[installation]: https://github.com/rdlowrey/Artax/wiki/Installation
[wiki]: https://github.com/rdlowrey/Artax/wiki
