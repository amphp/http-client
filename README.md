### WHAT IS IT?

Artax offers a full-featured HTTP/1.1 client, an object-oriented toolkit modeling the HTTP/1.1
protocol and a spec-compliant content-negotiation API for PHP 5.3+.

### HTTP Client

The Artax HTTP Client API is designed for standards-compliant HTTP resource traversal and RESTful web 
service consumption. At it's core, the Artax HTTP client aims to simplify HTTP communications without
obscuring the underlying protocol. It's the hope of the project maintainers that Artax will both
simplify your interactions with external HTTP resources and improve your understanding of how the
protocol works.

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

 - Compressed message transfers (gzip, deflate)
 - Integrated cookie storage
 - Automatic construction for multipart message bodies
 - Full proxy support
 - Transfer speed limits

### Content Negotiation

Artax provides a simple HTTP content negotiation module for negotiating appropriate language,
character-set, content-encoding and content-type from HTTP requests.


### PROJECT GOALS

* Implement an HTTP/1.1 Client built on raw sockets with no libcurl dependency;
* Model all relevant code on the HTTP/1.1 protocol as outlined in [RFC 2616][rfc2616];
* Provide an object-oriented alternative to the superglobals that make OO PHP web apps problematic;
* Provide a fully standard-compliant HTTP/1.1 content-negotiation API;
* Eschew the use of `static` entirely in favor of maximum testability and full API transparency;
* Build all components using [SOLID][solid], readable and 100% unit-tested code;


### REQUIREMENTS

* PHP 5.3+
* The [PHP-Datastructures][datastructures] library.
* The PHP `openssl` extension (for SSL/https requests)

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