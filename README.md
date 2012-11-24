### WHAT IS IT?

Artax is a full-featured HTTP/1.1 client built on the HTTP/1.1 protocol as specified in RFC 2616. 
Its API is designed to simplify standards-compliant HTTP resource traversal and RESTful web service
consumption without obscuring the underlying HTTP protocol. Checkout the [Artax Wiki][wiki] for full
coverage of the available features:

##### Features

 - No `cURL` or `libcurl` required; Artax uses sockets directly instead of PHP's `curl_*` bindings
 - Send and receive multiple requests in parallel for maximum throughput
 - Transparently follows redirects, chaining responses for access to the full redirect history
 - Access all request/response headers as well as all raw HTTP message data
 - Fully customizable SSL/TLS (https://) support
 - Stream request and response entity bodies for high-performance memory management
 - Maintain HTTP/1.1-compliant persistent connections with advanced connection management for 
long-running CLI applications
 - Support all standard HTTP/1.1 methods as well as custom methods
 - Standardized event broadcasts allowing custom plugins, cookie storage, etc.

###### In Development

 - Request and decompress gzip-encoded message bodies

###### Planned

 - Integrated cookie storage
 - Automatic construction for multipart message bodies
 - Proxy support
 - Manual DNS resolution for improved non-blocking performance

### PROJECT GOALS

* Implement an HTTP/1.1 Client built on raw sockets with no libcurl dependency;
* Model all relevant code on the HTTP/1.1 protocol as outlined in [RFC 2616][rfc2616];
* Provide an object-oriented alternative to the superglobals that make OO PHP web apps problematic;
* Eschew the use of `static` entirely in favor of maximum testability and full API transparency;
* Build all components using [SOLID][solid], readable and 100% unit-tested code;


### REQUIREMENTS

* PHP 5.3+
* The [PHP-Datastructures][datastructures] library.
* The PHP `openssl` extension for SSL/TLS (https) requests

You can find in-depth instructions for [verifying][requirements]/[installing][installation] these
requirements on the relevent Artax wiki pages.


### OTHER NOTES

> **NOTE:** Artax follows the Semantic Versioning Specification (SemVer) laid out at [semver.org](http://semver.org/)


### WHAT'S WITH THE NAME?

Children of the 1980s are likely familiar with [The NeverEnding Story][neverending] and may remember
the scene where Atreyu's faithful steed, Artax, died in the Swamp of Sadness. The name is an homage.

[rfc2616]: http://www.w3.org/Protocols/rfc2616/rfc2616.html
[datastructures]: https://github.com/morrisonlevi/PHP-Datastructures
[solid]: http://en.wikipedia.org/wiki/SOLID_(object-oriented_design) "S.O.L.I.D."
[neverending]: http://www.imdb.com/title/tt0088323/ "The NeverEnding Story"
[requirements]: https://github.com/rdlowrey/Artax/wiki/Requirements
[installation]: https://github.com/rdlowrey/Artax/wiki/Installation
[wiki]: https://github.com/rdlowrey/Artax/wiki