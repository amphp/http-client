### Release Roadmap

**IMPORTANT:** Artax is still unstable! The API is frozen but things may be broken due to the
current lack of testing. The lib is getting close to a v1.0.0 release candidate but it is not
there yet. Use at your own risk! The current release roadmap is as follows:


- v1.0.0 (Oct. 10, 2014 - tentative, depending on RC period length)

- v1.0.0-rc1 (Sep. 24, 2014)

Unforeseen bugfixes only. The RC period should be short-lived. If a non-trivial number of bugs are
discovered during the RC phase there may be multiple individual RC tags.

- v1.0.0-beta (Sep. 17, 2014)

    * bagder cert package no longer needed in composer.json
    * Fixed several bugs (see CHANGELOG)
    * Temporarily removed support for non-blocking file system entity streaming via php-uv
      due to unresolved segfaults in the extension.

- v1.0.0-alpha (Aug. 29, 2014)

First usable tagged release with a stable, frozen API. Things still missing:

    * Significant testing gaps
    * Non-blocking capabilities for filesystem IO
    * Multipart FormBody usage with file fields is currently broken


----------------------------------------------------------------------

Artax HTTP Client
=================

Artax is a full-featured HTTP/1.1 client as specified in RFC 2616. Its API is designed to simplify
standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the
underlying HTTP protocol. The code manually implements HTTP over TCP sockets; as such it has no
dependency on PHP's `curl_*` API and requires no non-standard PHP extensions.

##### Features

 - Exposes a fully non-blocking API
 - Pools persistent "keep-alive" connections
 - Transparently follows redirects
 - Requests and decodes gzipped entity bodies
 - Provides access to raw request/response headers and message data
 - Streams entity bodies for managing memory usage with large transfers
 - Supports all standard and custom HTTP method verbs
 - Trivializes HTTP form submissions
 - Implements secure-by-default TLS (https://) with userland support for new PHP 5.6 encryption
   features in older 5.4 and 5.5 versions
 - Offers advanced connection limiting options on a per-host basis
 - Transparently supports cookies and sessions
 - Functions seamlessly behind HTTP proxy servers

##### Project Goals

* Model all code as closely as possible to the relevant HTTP protocol RFCs;
* Implement an HTTP/1.1 client built on raw socket streams with no libcurl dependency;
* Build all components using SOLID, readable and tested code;

##### Installation

```bash
$ git clone https://github.com/rdlowrey/Artax.git
$ cd Artax
$ composer.phar install
```

The relevant composer/packagist lib is `rdlowrey/artax`.



Examples
--------

Note that extensive code examples are available in the [`examples/`](examples) directory.

##### Simple URI GET

Often we only care about simple GET retrieval. For such cases Artax accepts a basic HTTP URI string
as the request parameter:

```php
<?php

try {
    $response = (new Artax\Client)->request('http://www.google.com')->wait();
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
when sending an `Artax\Request` is the absolute *http://* or *https://* request URI:

```php
<?php

try {
    $request = (new Artax\Request)
        ->setUri('http://httpbin.org/post')
        ->setProtocol('1.1')
        ->setMethod('POST')
        ->setBody('woot!')
        ->setAllHeaders([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cookie' => ['Cookie1=val1', 'Cookie2=val2']
        ])
    ;

    $response = (new Artax\Client)->request($request)->wait();

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

We can easily submit this form using the `Artax\FormBody` API:

```php
<?php

$body = (new Artax\FormBody)
    ->addField('name', 'Zoroaster')
    ->addFileField('file1', '/hard/path/to/some/file1')
    ->addFileField('file2', '/hard/path/to/some/file2')
;

$request = (new Artax\Request)
    ->setUri('http://httpbin.org/post')
    ->setMethod('POST')
    ->setBody($body)
;

$response = (new Artax\Client)->request($request)->wait();
```

##### Parallel Requests

It's important to understand that *all* Artax requests are processed in parallel regardless of
whether or not you invoke them at the same time. The following two operations will do the exact
same thing:

```php
<?php
$client = new Artax\Client;

// Here we pass dispatch two requests at the same time
$arrayOfPromises = $client->requestMulti([
    'http://www.google.com',
    'http://www.bing.com'
]);
list($googleResponse, $bingResponse) = After\all($arrayOfPromises)->wait();


// Here we invoke the two requests individually
$googlePromise = $client->request('http://www.google.com');
$bingPromise = $client->request('http://www.bing.com');
$comboPromise = After\all([$googlePromise, $bingPromise]);
list($googleResponse, $bingResponse) = $comboPromise->wait();
```

Remember that `Artax\Client::request()` *always* returns a promise instance. So if we want to
specify individualized callbacks for progress events on those promises we're perfectly able to
do so. In the below example we use the `Promise::when()` method (which accepts an error-first
callback) to react to the completion of an individual response:

```php
<?php
$client = new Artax\Client;

list($googlePromise, $bingPromise) = $client->request([
    'http://www.google.com',
    'http://www.bing.com'
]);

$googleResponse = null;
$googlePromise->when(function($error, $result) use (&$googleResponse) {
    if ($error) {
        // the request failed for some reason. Get the exception message
        echo $error->getMessage();
    } else {
        // Do something with the completed $response here
        $googleResponse = $result;
    }
});

// After\all() combines our array of promises into a single promise that will
// fail if any one of the individual promises fails. Remember that Promise::wait()
// will throw if resolution fails!
$comboPromise = After\all([$googlePromise, $bingPromise]);
list($googleResponse2, $bingResponse) = $comboPromise->wait();

// They're the same instance!
assert($googleResponse === $googleResponse2);
```

Note that resolving a combined promise results in the same array keys as those passed to the
combinator function. Consider:


```php
<?php

$arrayOfPromises = (new Artax\Client)->request([
    'google'    => 'http://www.google.com',
    'news'      => 'http://news.google.com',
    'bing'      => 'http://www.bing.com',
    'yahoo'     => 'https://www.yahoo.com',
]);

// After\all() combines our array of promises into a single promise that
// will fail if any one of the individual promises fails
$responses = After\all($promises)->wait();

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

Once again we note that `Artax\Client::request()` always returns a promise instance. This means
we can use the `Promise::watch()` method to observe updates/events for a particular request:

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
        case Artax\Notify::SOCK_PROCURED:
            echo "SOCK_PROCURED\n";
            break;
        case Artax\Notify::SOCK_DATA_IN:
            echo "SOCK_DATA_IN\n";
            break;
        case Artax\Notify::SOCK_DATA_OUT:
            echo "SOCK_DATA_OUT\n";
            break;
        case Artax\Notify::REQUEST_SENT:
            echo "REQUEST_SENT\n";
            break;
        case Artax\Notify::RESPONSE_HEADERS:
            echo "RESPONSE_HEADERS\n";
            break;
        case Artax\Notify::RESPONSE_BODY_DATA:
            echo "RESPONSE_BODY_DATA\n";
            break;
        case Artax\Notify::RESPONSE:
            echo "RESPONSE\n";
            break;
        case Artax\Notify::REDIRECT:
            echo "REDIRECT\n";
            break;
        case Artax\Notify::ERROR:
            echo "ERROR\n";
            break;
    }
}

/**
 * Progress updates are distributed by the promise. To "listen" for update
 * notifications simply pass a callback to Promise::watch() as demonstrated
 * below.
 */
$promise = (new Artax\Client)->request('http://www.google.com');
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

@TODO Discuss Client-wide assignment

@TODO Discuss per-request assignment


##### Progress Bars

Generating a progress bar depends on a few details from the HTTP spec regarding message size. To
make this easier for end users Artax exposes the `Artax\Progress` object which makes generating
a usable progress bar on a per-request basis trivial. Consider:

```php
<?php
$promise = (new Artax\Client)->request($request);
$promise->watch(new Artax\Progress(function($update) {
    printf(
        "\r%s %s%%\r",
        $update['bar'],
        round($update['percent_complete'] * 100)
    );
}));
$response = $promise->wait();

```
