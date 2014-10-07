Artax
=====

Artax is an asynchronous HTTP/1.1 client. Its API simplifies standards-compliant HTTP resource
traversal and RESTful web service consumption without obscuring the underlying protocol. The library
manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

##### Features

 - Requests asynchronously
 - Pools persistent "keep-alive" connections
 - Transparently follows redirects
 - Decodes gzipped entity bodies
 - Exposes raw headers and message data
 - Streams entity bodies for managing memory usage with large transfers
 - Supports all standard and custom HTTP method verbs
 - Simplifies HTTP form submissions
 - Implements secure-by-default TLS (https://) with userland support for new PHP 5.6 encryption
   features in older PHP versions
 - Limits connections per-host to avoid IP bans in scraping contexts
 - Supports cookies and sessions
 - Functions seamlessly behind HTTP proxy servers

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
--------

Note that extensive code examples are available in the [`examples/`](examples) directory.

##### Simple URI GET

Often we only care about simple GET retrieval. For such cases Artax accepts a basic HTTP URI string
as the request parameter:

```php
<?php

try {
    $response = (new Amp\Artax\Client)->request('http://www.google.com')->wait();
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

For non-trivial requests Artax allows you to construct messages piece-by-piece. This example
sets the request method to POST and assigns an entity body. HTTP veterans will notice that
we don't bother to set a `Content-Length` or `Host` header. Artax will automatically add/normalize
missing headers for us so we don't need to worry about it. The only property that _MUST_ be assigned
when sending an `Amp\Artax\Request` is the absolute *http://* or *https://* request URI:

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

    $response = (new Amp\Artax\Client)->request($request)->wait();

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
    ->addFileField('file1', '/hard/path/to/some/file1')
    ->addFileField('file2', '/hard/path/to/some/file2')
;

$request = (new Amp\Artax\Request)
    ->setUri('http://httpbin.org/post')
    ->setMethod('POST')
    ->setBody($body)
;

$response = (new Amp\Artax\Client)->request($request)->wait();
```

##### Concurrent Requests

It's important to understand that *all* artax requests are processed concurrently regardless of
whether or not you invoke them at the same time. Because artax utilizes the amphp concurrency
framework we have a few options for flow control when requesting multiple resources at once:


**Generators**

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

```php
<?php
$client = new Amp\Artax\Client;

// Dispatch two requests at the same time
$promiseArray = $client->requestMulti([
    'http://www.google.com',
    'http://www.bing.com',
]);

try {
    list($google, $bing) = Amp\all($promiseArray)->wait();
    var_dump($google->getStatus(), $bing->getStatus());
} catch (Exception $e) {
    echo $e;
}
```

Note that resolving a combined promise always results in the same array keys as those passed to the
combinator function (`Amp\all()` in this example). Consider:

```php
<?php

$promiseArray = (new Amp\Artax\Client)->request([
    'google'    => 'http://www.google.com',
    'news'      => 'http://news.google.com',
    'bing'      => 'http://www.bing.com',
    'yahoo'     => 'https://www.yahoo.com',
]);

$responses = Amp\all($promiseArray)->wait();

foreach ($responses as $key => $response) {
    printf(
        "%s | HTTP/%s %d %s\n",
        $key, // <-- these keys will match those from our original request array
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );
}
```


##### Progress Events

Because responses are retrieved asynchronously the artax client *always* returns a promise (or array
of promises) when requesting an HTTP resource. This means we can employ the `Promise::watch()`
method to observe individual updates for a particular requests:

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
$response = $promise->wait();

printf(
    "\nResponse: HTTP/%s %d %s\n\n",
    $response->getProtocol(),
    $response->getStatus(),
    $response->getReason()
);
```


##### Option Assignment

@TODO


##### Progress Bars

Generating a progress bar depends on a few details from the HTTP spec regarding message size. To
make this easier for end users Artax exposes the `Amp\Artax\Progress` object which makes generating
a usable progress bar on a per-request basis trivial. Consider:

```php
<?php
$promise = (new Amp\Artax\Client)->request($request);
$promise->watch(new Amp\Artax\Progress(function($update) {
    printf(
        "\r%s %s%%\r",
        $update['bar'],
        round($update['fraction_complete'] * 100)
    );
}));
$response = $promise->wait();

```
