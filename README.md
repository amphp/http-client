Artax
=====

Artax is an asynchronous HTTP/1.1 client built on the [amp concurrency framework][1]. Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

##### Features

 - Requests asynchronously for full single-threaded concurrency
 - Pools persistent keep-alive connections
 - Transparently follows redirects
 - Decodes gzipped entity bodies
 - Exposes raw headers and message data
 - Streams entity bodies for memory management with large transfers
 - Supports all standard and custom HTTP method verbs
 - Simplifies HTTP form submissions
 - Implements secure-by-default TLS (https://) with userland support for new PHP 5.6 encryption
   features in older PHP versions
 - Limits connections per host to avoid IP bans in scraping contexts
 - Supports cookies and sessions
 - Functions seamlessly behind HTTP proxies

##### Project Goals

* Model all code as closely as possible to the relevant HTTP protocol RFCs;
* Implement an HTTP/1.1 client built on raw socket streams with no libcurl dependency;
* Build all components using SOLID, readable and tested code;

##### Installation

```bash
$ git clone https://github.com/amphp/artax.git
$ cd artax
$ composer.phar install
```

The relevant packagist lib is `amphp/artax`.



Examples
------------

More extensive code examples reside in the [`examples`](examples) directory.

##### Simple URI GET

Often we only care about simple GET retrieval. For such cases artax accepts a basic HTTP URI string
as the request parameter:

```php
<?php

try {
    $client = new Amp\Artax\Client;
    $promise = $client->request('http://www.google.com');
    $response = Amp\wait($promise);
    printf(
        "\nHTTP/%s %d %s\n",
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );
} catch (Exception $error) {
    echo $error;
}

```

##### Customized Request Messages

For more advanced requests artax allows incremental message construction. This example
sets the request method to POST and assigns an entity body. HTTP veterans will notice that
we don't bother to set a `Content-Length` or `Host` header. This is unnecessary because artax will automatically add/normalize missing headers for us so we don't need to worry about it. The only property that _MUST_ be assigned when sending an `Amp\Artax\Request` is the absolute *http://* or *https://* request URI:

```php
<?php

try {
    $request = (new Amp\Artax\Request)
        ->setUri('http://httpbin.org/post')
        ->setProtocol('1.1')
        ->setMethod('POST')
        ->setBody('woot!')
        ->setAllHeaders([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cookie' => ['Cookie1=val1', 'Cookie2=val2']
        ])
    ;

    $response = Amp\wait((new Amp\Artax\Client)->request($request));

} catch (Exception $error) {
    echo $error;
}

```


##### Form Submission

Assume `httpbin.org/post` contains the following HTML form:

```html
<form action="http://httpbin.org/post" enctype="multipart/form-data" method="post">
   <P>
   What is your name? <input type="text" name="name"><br>
   What files are you sending?<br>
   <input type="file" name="file1"><br>
   <input type="file" name="file2"><br>
   <input type="submit" value="Send">
 </form>
```

We can easily submit this form using the `Amp\Artax\FormBody` API:

```php
<?php

$body = (new Amp\Artax\FormBody)
    ->addField('name', 'Zoroaster')
    ->addFile('file1', '/hard/path/to/some/file1')
    ->addFile('file2', '/hard/path/to/some/file2')
;

$request = (new Amp\Artax\Request)
    ->setUri('http://httpbin.org/post')
    ->setMethod('POST')
    ->setBody($body)
;

$response = Amp\wait((new Amp\Artax\Client)->request($request));
```

### Concurrency

You may have noticed from the previous examples that we called `Amp\wait()` on our `Client::request()` return result. This is necessary because the artax client never actually returns a response directly; instead, it returns an `Amp\Promise` instance to represent the future response. It's important to understand that *all* artax requests are processed concurrently. The synchronous `Amp\wait()` call simply allows us to sit around and wait for the response to resolve before proceeding. However, it's often undesirable to synchronously wait on a response. In cases where we want non-blocking concurrency  we have a few options ...


**Generators**

The [amp concurrency framework][1] run loop always acts as a co-routine for generators yielding `Amp\Promise` instances (e.g. our artax client) or other generators. Here we use a generator to make our code feel synchronous even though it's doing multiple things concurrently. If something goes wrong with one of the requests the relevant exception will be thrown back into our generator:

```php
<?php
Amp\run(function() {
    $client = new Amp\Artax\Client;

    // Dispatch two requests at the same time
    $promiseArray = $client->requestMulti([
        'http://www.google.com',
        'http://www.bing.com',
    ]);

    try {
        // Yield control until all requests finish (magic sauce)
        list($google, $bing) = (yield Amp\all($promiseArray));
        var_dump($google->getStatus(), $bing->getStatus());
    } catch (Exception $e) {
        echo $e;
    }
});
```

**Synchronous Wait**

All [amp][1]  `Promise` instances expose the ability to synchronously `Amp\wait()` for resolution. While blocking for a result isn't recommended in fully non-blocking applications it can trivialize the use of non-blocking libraries like artax in synchronous contexts.

```php
<?php
$client = new Amp\Artax\Client;

// Dispatch two requests at the same time
$promiseArray = $client->requestMulti([
    'http://www.google.com',
    'http://www.bing.com',
]);

try {
    // Amp\all() flattens an array of promises into a new promise
    // that on which we can Amp\wait()
    list($google, $bing) = Amp\wait(Amp\all($promiseArray));
    var_dump($google->getStatus(), $bing->getStatus());
} catch (Exception $e) {
    echo $e;
}
```

Note that resolving a combined promise always results in the same array keys as those passed to the
combinator function (`Amp\all()` in this example). Consider:

```php
<?php

$promiseArray = (new Amp\Artax\Client)->requestMulti([
    'google'    => 'http://www.google.com',
    'news'      => 'http://news.google.com',
    'bing'      => 'http://www.bing.com',
    'yahoo'     => 'https://www.yahoo.com',
]);

$responses = Amp\wait(Amp\all($promiseArray));

foreach ($responses as $key => $response) {
    printf(
        "%s | HTTP/%s %d %s\n",
        $key, // <-- these keys match those from our original request array
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );
}
```

**Callbacks**

While callbacks are sometimes considered a clumsy way to manage concurrency, it's possible to use them to react to future response resolutions inside the context of the [amp][1] event loop. All `Amp\Promise` instances expose a `when()` method accepting an error-first callback that will be invoked upon promise resolution. Consider:

```php
<?php
use Amp\Artax\Client;

$reactor = Amp\reactor();
$client = new Client($reactor);
$request = (new Amp\Artax\Request)
    ->setUri('http://www.google.com')
    ->setHeader('Connection', 'close')
;
$promise = $client->request($request);
$promise->when(function(Exception $error = null, $response = null) {
    if ($error) {
        // something went wrong :(
    } else {
        printf("Response complete: %d\n", $response->getStatus());
    }
});

// Nothing will happen until the event reactor runs.
$reactor->run();
```


**Progress Events**

We can use the `Amp\Promise::watch()` method to subscribe to request progress updates while our responses resolve. Consider:

```php
<?php
/**
 * All Artax progress notifications take the form of an indexed array. The
 * first element is a notification code denoting the type of event being
 * broadcast. The remaining items are data associated with the event at hand.
 */
function myNotifyCallback(array $notifyData) {
    $event = array_shift($notifyData);
    switch ($event) {
        case Amp\Artax\Notify::SOCK_PROCURED:
            echo "SOCK_PROCURED\n";
            break;
        case Amp\Artax\Notify::SOCK_DATA_IN:
            echo "SOCK_DATA_IN\n";
            break;
        case Amp\Artax\Notify::SOCK_DATA_OUT:
            echo "SOCK_DATA_OUT\n";
            break;
        case Amp\Artax\Notify::REQUEST_SENT:
            echo "REQUEST_SENT\n";
            break;
        case Amp\Artax\Notify::RESPONSE_HEADERS:
            echo "RESPONSE_HEADERS\n";
            break;
        case Amp\Artax\Notify::RESPONSE_BODY_DATA:
            echo "RESPONSE_BODY_DATA\n";
            break;
        case Amp\Artax\Notify::RESPONSE:
            echo "RESPONSE\n";
            break;
        case Amp\Artax\Notify::REDIRECT:
            echo "REDIRECT\n";
            break;
        case Amp\Artax\Notify::ERROR:
            echo "ERROR\n";
            break;
    }
}

/**
 * Progress updates are distributed by the promise. To "listen" for update
 * notifications simply pass a callback to Promise::watch() as demonstrated
 * below.
 */
$promise = (new Amp\Artax\Client)->request('http://www.google.com');
$promise->watch('myNotifyCallback');
$response = Amp\wait($promise);

printf(
    "\nResponse: HTTP/%s %d %s\n\n",
    $response->getProtocol(),
    $response->getStatus(),
    $response->getReason()
);
```

The above example will output updates to the console throughout the request-response cycle before the final `printf()` call prints the status line for the eventual response.

### Options

Client behavior may be modified in two ways:

1. Modify behavior for all subsequent requests via `Client::setOption()`;
2. Pass an options array to the second parameter of `Client::request()`.

```php
<?php
use Amp\Artax\Client;

$client = new Client;

// Output all raw request messages to the console when sending
$client->setOption(Client::OP_VERBOSITY, Client::VERBOSE_SEND);

// Also output the raw response to the console for this one request
$promise = $client->request('http://www.google.com', $options = [
    Client::OP_VERBOSITY => Client::VERBOSE_ALL
]);
```

A brief summary of the available options follows.

| Option Constant       | Description                                       |
| --------------------- | --------------------------------------------------|
| OP_BINDTO                   | Specify the source IP address to which TCP socket connections will be bound (useful when you have multiple IPs). |
| OP_MS_CONNECT_TIMEOUT       | How long in milliseconds before a connection attempt should timeout. |
| OP_HOST_CONNECTION_LIMIT    | How many simultaneous connections will be open to any one unique host name (helpful to prevent your IP from being banned). |
| OP_MS_KEEP_ALIVE_TIMEOUT    | How long in milliseconds to keep persistent connections alive after a request. If not used within this time frame the socket will be closed. |
| OP_PROXY_HTTP               | An optional IP:PORT through which to proxy unencrypted HTTP requests. Note that artax auto-detects system wide proxy settings. |
| OP_PROXY_HTTPS              | An optional IP:PORT through which to proxy encrypted HTTPS requests. Note that artax auto-detects system wide proxy settings. |
| OP_AUTO_ENCODING            | A boolean (default: `true`) controlling whether or not to request and decode gzip entity bodies. |
| OP_MS_TRANSFER_TIMEOUT      | How long in milliseconds before an individual transfer is cancelled (disabled by default). |
| OP_MS_100_CONTINUE_TIMEOUT  | How long to wait for a 100 Continue response before proceeding when using an Expect header. |
| OP_EXPECT_CONTINUE          | A boolean (default: `false`) controlling whether or not to send an Expect header with requests containing an entity body. |
| OP_FOLLOW_LOCATION          | A boolean (default: `true`) responsible for the server automatically following redirects. |
| OP_AUTO_REFERER             | A boolean (default: `true`) responsible for automatically adding a Referer header when following redirects. |
| OP_BUFFER_BODY              | A boolean (default: `true`) which, if enabled, will result in the full response entity body being buffered as a string upon completion. If disabled a stream resource is returned from `Response::getBody()` |
| OP_DISCARD_BODY             | Whether or not to store response entity bodies. This option can be used in conjunction with `Promise::watch()` to handle long-running/streaming response bodies (e.g. chunked twitter API feeds). |
| OP_IO_GRANULARITY           | The maximum number of bytes to read/write to when performing IO operations on socket connections |
| OP_VERBOSITY                | A bitmask of client constants controlling raw message output to the console. |
| OP_COMBINE_COOKIES          | A boolean (default: `true`) controlling whether or not cookie headers are combined into a single header when sending requests. |
| OP_CRYPTO                   | An array controlling TLS encryption options. |
| OP_DEFAULT_USER_AGENT       | A string set for each request's User-Agent if a UA has not already been provided. |
| OP_BODY_SWAP_SIZE           | The size, in bytes, that should be used for body swap. |
| OP_MAX_HEADER_BYTES         | The maximum size, in bytes, for a Response header. |
| OP_MAX_BODY_BYTES           | The maximum size, in bytes, for a Response body. |


### Miscellaneous

#### Progress Bars

Generating a progress bar depends on a few details from the HTTP spec regarding message size. To
make this easier for end users Artax exposes the `Amp\Artax\Progress` object which makes generating
a usable progress bar on a per-request basis trivial. Consider:

```php
<?php
$uri = "http://www.google.com";
$promise = (new Amp\Artax\Client)->request($uri);
$promise->watch(new Amp\Artax\Progress(function($update) {
    echo "\r", round($update['fraction_complete'] * 100), " percent complete \r";
}));
$response = Amp\wait($promise);
```

The following keys are available in the `$update` array sent from the `Progress` object:

-  request_state
- connected_at
- bytes_rcvd
- bytes_per_second
- header_length
- content_length
- fraction_complete
- redirect_count




  [1]: https://github.com/amphp/amp
