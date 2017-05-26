# artax

Artax is an asynchronous HTTP/1.1 client built on the [amp concurrency framework](https://github.com/amphp/amp). Its API simplifies standards-compliant HTTP resource traversal and RESTful web service consumption without obscuring the underlying protocol. The library manually implements HTTP over TCP sockets; as such it has no dependency on `ext/curl`.

## Features

 - Requests asynchronously for full single-threaded concurrency
 - Pools persistent keep-alive connections
 - Transparently follows redirects
 - Decodes gzipped entity bodies
 - Exposes raw headers and message data
 - Streams entity bodies for memory management with large transfers
 - Supports all standard and custom HTTP method verbs
 - Simplifies HTTP form submissions
 - Implements secure-by-default TLS (`https://`)
 - Limits connections per host to avoid IP bans in scraping contexts
 - Supports cookies and sessions
 - Functions seamlessly behind HTTP proxies

## Project Goals

 - Model all code as closely as possible to the relevant HTTP protocol RFCs
 - Implement an HTTP/1.1 client built on raw socket streams with no `libcurl` dependency

## Installation

```
composer require amphp/artax
```

## Examples

More extensive code examples reside in the [`examples`](examples) directory.

### Simple URI GET

Often we only care about simple GET retrieval. For such cases Artax accepts a basic HTTP URI string as the request parameter:

```php
<?php

try {
    $client = new Amp\Artax\Client;
    $promise = $client->request('http://www.google.com');
    $response = Amp\Promise\wait($promise);
    
    printf(
        "\nHTTP/%s %d %s\n",
        $response->getProtocolVersion(),
        $response->getStatus(),
        $response->getReason()
    );
} catch (Exception $error) {
    echo $error;
}
```

### Customized Request Messages

For more advanced requests Artax allows incremental message construction. This example sets the request method to POST and assigns an entity body. HTTP veterans will notice that we don't bother to set a `Content-Length` or `Host` header. This is unnecessary because Artax will automatically add/normalize missing headers for us so we don't need to worry about it. The only property that _MUST_ be assigned when sending an `Amp\Artax\Request` is the absolute `http://` or `https://` request URI:

```php
<?php

try {
    $request = (new Amp\Artax\Request('http://httpbin.org/post', 'POST'))
        ->withProtocolVersion('1.1')
        ->withBody('woot!')
        ->withAllHeaders([
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cookie' => ['Cookie1=val1', 'Cookie2=val2']
        ]);

    $response = Amp\Promise\wait((new Amp\Artax\Client)->request($request));
} catch (Exception $error) {
    echo $error;
}
```

### Form Submission

Assume `https://httpbin.org/post` contains the following HTML form:

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

$body = new Amp\Artax\FormBody;
$body->addField('name', 'Zoroaster');
$body->addFile('file1', '/hard/path/to/some/file1');
$body->addFile('file2', '/hard/path/to/some/file2');

$request = (new Amp\Artax\Request('http://httpbin.org/post', 'POST'))
    ->withBody($body);

$response = Amp\Promise\wait((new Amp\Artax\Client)->request($request));
```

### Options

Client behavior may be modified in two ways:

1. Modify behavior for all subsequent requests via `Client::setOption()`;
2. Pass an options array to the second parameter of `Client::request()`.

```php
<?php
use Amp\Artax\Client;

$client = new Client;

// Set the default maximum body size to 64 kB
$client->setOption(Client::OP_MAX_BODY_BYTES, 64 * 1024);

// Allow a larger body for this one request
$promise = $client->request('http://www.google.com', $options = [
    Client::OP_MAX_BODY_BYTES => 1024 * 1024,
]);
```

A brief summary of the available options follows.

| Option Constant       | Description                                       |
| --------------------- | --------------------------------------------------|
| OP_BINDTO                   | Specify the source IP address to which TCP socket connections will be bound (useful when you have multiple IPs). |
| OP_CONNECT_TIMEOUT          | How long in milliseconds before a connection attempt should timeout. |
| OP_KEEP_ALIVE_TIMEOUT       | How long in milliseconds to keep persistent connections alive after a request. If not used within this time frame the socket will be closed. |
| OP_PROXY_HTTP               | An optional IP:PORT through which to proxy unencrypted HTTP requests. Note that artax auto-detects system wide proxy settings. |
| OP_PROXY_HTTPS              | An optional IP:PORT through which to proxy encrypted HTTPS requests. Note that artax auto-detects system wide proxy settings. |
| OP_TRANSFER_TIMEOUT         | How long in milliseconds before an individual transfer is cancelled (15 seconds by default). |
| OP_FOLLOW_LOCATION          | A boolean (default: `true`) responsible for the server automatically following redirects. |
| OP_AUTO_REFERER             | A boolean (default: `true`) responsible for automatically adding a Referer header when following redirects. |
| OP_DISCARD_BODY             | Whether or not to store response entity bodies. This option can be used if you don't care about the body and want to save memory. |
| OP_IO_GRANULARITY           | The maximum number of bytes to read/write to when performing IO operations on socket connections |
| OP_CRYPTO                   | An array controlling TLS encryption options. |
| OP_DEFAULT_USER_AGENT       | A string set for each request's User-Agent if a UA has not already been provided. |
| OP_MAX_HEADER_BYTES         | The maximum size, in bytes, for a Response header. |
| OP_MAX_BODY_BYTES           | The maximum size, in bytes, for a Response body. |
