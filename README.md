### WHAT IS IT?

Artax offers a full-featured HTTP/1.1 client, an object-oriented toolkit modeling the official HTTP
protocol and a spec-compliant content-negotiation API for PHP 5.3+.

##### HTTP Client

The Artax HTTP Client is an object-oriented API enabling intuitive, standards-compliant HTTP 
resource traversal and RESTful web service consumption a triviality. Some of the HTTP Client's
features include:

 - No `cURL` or `libcurl` required; Artax uses sockets directly instead of fiddling with PHP's `curl_*` bindings
 - Send and receive multiple requests in parallel for maximum throughput
 - Transparently follow redirects, chaining redirected responses for a full-view of the request location history
 - Access all request/response headers as well as all raw HTTP message data
 - Fully customizable SSL (https) support
 - Stream request and response entity bodies for high-performance memory management
 - All connections are kept-alive and reused unless closed by the remote server
 - Supports all HTTP/1.1 methods as well as custom methods
 - Advanced persistent connection management for long-running CLI applications
 - Standardized event broadcasts allow custom plugins like caching, cookie storage, etc.
 - Secure SSL/TLS protocol implementation *by default*

##### Content Negotiation

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

Artax requires PHP 5.3+ and depends on the [PHP-Datastructures][datastructures] repository. 
Additionally, the Artax HTTP Client requires the `openssl` extension for encrypted SSL/TLS (HTTPS) 
requests. If `openssl` is unavailable, only unencrypted HTTP requests can be made. You can verify
the status of the `openssl` extension in your PHP install with the following code snippet:

```php
<?php
var_dump(extension_loaded('openssl')); // bool(true)
```

> **IMPORTANT:** The Artax Client will not function correctly in the presence of string function overloading 
via the `mbstring.func_overload` php.ini directive. This directive is an *ugly hack* for handling
multi-byte strings and you should not be using it! If you're unsure about whether or not you've
enabled string function overloading, you can check that the following statement evaluates to `false`:

```php
<?php
var_dump(extension_loaded('mbstring') && (ini_get('mbstring.func_overload') & 2)); // bool(false)
```

If the above outputs `bool(true)`, string function overloading is turned on in your PHP install and
the Artax Client will yield dubious results at best.


### OTHER NOTES

> **NOTE:** Auryn follows the Semantic Versioning Specification (SemVer) laid out at [semver.org](http://semver.org/)


### WHAT'S WITH THE NAME?

Children of the 1980s are likely familiar with [The NeverEnding Story][neverending] and may remember
the scene where Atreyu's faithful steed, Artax, died in the Swamp of Sadness. The name is an homage.

[rfc2616]: http://www.w3.org/Protocols/rfc2616/rfc2616.html
[datastructures]: https://github.com/morrisonlevi/PHP-Datastructures
[solid]: http://en.wikipedia.org/wiki/SOLID_(object-oriented_design) "S.O.L.I.D."
[neverending]: http://www.imdb.com/title/tt0088323/ "The NeverEnding Story"