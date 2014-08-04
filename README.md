
Woah, slow down, cowboy! **THIS IS THE DEV BRANCH** and this README has not yet been updated. It
reflects code from the master branch. If you want to know how to use this branch you need to check
out the [example files](https://github.com/rdlowrey/Artax/tree/dev/examples).


----------------------------------------------------------------------------------------------------


Artax HTTP Client
=================

Artax is a full-featured HTTP/1.1 client as specified in RFC 2616. Its API is designed to simplify
standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the
underlying HTTP protocol. The code manually implements the HTTP over TCP sockets; as such it has no
dependency on PHP's `curl_*` API and requires no non-standard PHP extensions.

#### FEATURES

 - Full non-blocking API
 - Pools persistent "keep-alive" connections
 - Transparently follows redirects
 - Requests and decodes gzipped entity bodies
 - Provides access to raw request/response headers and message data
 - Streams entity bodies for managing memory usage with large transfers
 - Supports all standard and custom HTTP method verbs
 - Trivializes HTTP form submissions
 - Provides fully customizable and secure-by-default TLS (https://) support
 - Offers advanced connection limiting options on a per-host basis
 - Transparently supports cookies and sessions
 - Fully functional behind HTTP proxy servers

#### PROJECT GOALS

* Model all code as closely as possible to the relevant HTTP protocol RFCs;
* Implement an HTTP/1.1 client built on raw sockets with no libcurl dependency;
* Build all components using [SOLID][solid], readable and tested code;

#### INSTALLATION

###### Git:

```bash
$ git clone https://github.com/rdlowrey/Artax.git
$ cd Artax
$ composer.phar install
```

###### Composer:

```json
    "require": {
        "rdlowrey/artax": "~v0.8.0",
    },
```



@TODO
----------------------------------------------------------------------------------------------------
Haven't updated anything below here yet




#### BASIC USAGE

###### Synchronous GET Request

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

###### Synchronous Customized POST Request

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


###### Form Submission

Assume that `httpbin.org/post` contains the following HTML form:

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

$body = new Artax\FormBody;

$body->addField('name', 'Zoroaster');
$body->addFileField('file1', '/hard/path/to/some/file1');
$body->addFileField('file2', '/hard/path/to/some/file2');

$client = new Artax\Client;
$request = new Artax\Request;
$request->setBody($body);
$request->setUri('http://httpbin.org/post');
$request->setMethod('POST');

$response = $client->request($request);
```

It's *important* to note that `Artax\FormBody` will **stream** any file fields you specify as
part of your form submission. This means you can easily upload huge files without issue.

###### Synchronous Parallel Requests

@TODO add brief description

```php
<?php

$arrayOfPromises = (new Artax\Client)->request([
    'google'    => 'http://www.google.com',
    'news'      => 'http://news.google.com',
    'bing'      => 'http://www.bing.com',
    'yahoo'     => 'https://www.yahoo.com',
]);

// After\all() combines our array of promises into a single promise that will fail if any
// one of the individual promises fails
$responses = After\all($promises)->wait();

foreach ($responses as $key => $response) {
    printf(
        "%s | HTTP/%s %d %s\n",
        $key,
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );
}

```

###### Progress Bar

@TODO add brief description

```php

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


#### WHAT'S WITH THE NAME?

Children of the 1980s are likely familiar with [The NeverEnding Story][neverending] and may remember
the scene where Atreyu's faithful steed, Artax, died in the Swamp of Sadness. The name is an homage.




[rfc2616]: http://www.w3.org/Protocols/rfc2616/rfc2616.html
[solid]: http://en.wikipedia.org/wiki/SOLID_(object-oriented_design) "S.O.L.I.D."
[neverending]: http://www.imdb.com/title/tt0088323/ "The NeverEnding Story"

