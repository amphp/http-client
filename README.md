### WHAT IS IT?

Artax is an object-oriented HTTP toolkit and full-featured HTTP client. PHP's lamentable superglobal
implementations make writing S.O.L.I.D. web applications in PHP almost impossible. Artax models
the HTTP protocol using objects to maximize testability and avoid the pitfalls of littering OO code
with unencapsulated superglobals.

The Artax Client acts as a drop-in replacement for the woeful `curl_*` API to allow intuitive, 
object-oriented and standards-compliant HTTP resource traversal. Artax makes consuming RESTful web
services a triviality.

### DEPENDENCIES

Artax requires PHP 5.3 or higher and depends on the [PHP-Datastructures][datastructures] repository.

### CLIENT FEATURES

 - No `cURL` or `libcurl` required; Artax uses sockets directly instead of tittering with PHP's
`curl_*` bindings
 - Send and receive multiple requests in parallel for maximum throughput
 - Transparently follows redirects and chains redirected responses
 - Access all request/response headers as well as all raw HTTP message data
 - Fully customizable SSL (https) support
 - Stream request and response entity bodies for high-performance memory management
 - All connections are kept-alive and reused unless closed by the remote server
 - Supports all HTTP/1.1 methods and custom methods
 - Advanced persistent connection management for long-running CLI applications
 - Standardized event broadcasts allow custom plugins like caching, cookie storage, etc.
 - Send requests through proxy servers

### BASIC HTTP CLIENT USAGE

```
<?php

use Spl\HashingMediator, Artax\Client, Artax\ClientException, Artax\Http\StdRequest;
require 'bootstrap.php'; // hard path to the Artax bootstrap file

$mediator = new HashingMediator();
$client = new Client($mediator);
$request = new StdRequest('http://www.google.com', 'GET');

try {
    $response = $client->send($request);
} catch (ClientException $e) {
    echo $e->getMessage() . PHP_EOL;
}
```

### PROJECT GOALS

* Provide an object-oriented modeling of the HTTP protocol as outlined in [RFC 2616][rfc2616]
* Eschew the use of `static` entirely in favor of maximum testability and full API transparency;
* Build all components using [SOLID][solid], readable and 100% unit-tested code.
* Implement pluggable, evented classes without inhibiting linear cause/effect design;

### WHAT'S WITH THE NAME?

Children of the 1980s are likely familiar with [The NeverEnding Story][neverending] and may remember
the scene where Atreyu's faithful steed, Artax, died in the Swamp of Sadness. The name is an homage.

[rfc2616]: http://www.w3.org/Protocols/rfc2616/rfc2616.html
[datastructures]: https://github.com/morrisonlevi/PHP-Datastructures
[solid]: http://en.wikipedia.org/wiki/SOLID_(object-oriented_design) "S.O.L.I.D."
[neverending]: http://www.imdb.com/title/tt0088323/ "The NeverEnding Story"