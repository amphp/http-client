<?php

use function Amp\Promise\wait;

require __DIR__ . '/../vendor/autoload.php';

try {
    // Instantiate the HTTP client. In order to persist cookies beyond the life of the
    // Client object we need to instantiate the client with our own cookie jar. The
    // second constructor argument is the CookieJar implementation we wish to use.
    // If not specified, the client simply uses the default in-memory cookie jar.
    $cookieJar = new Amp\Artax\Cookie\FileCookieJar('/tmp/path/to/my_cookies.txt');
    $client = new Amp\Artax\Client(null, $cookieJar);

    // This request will receive and store google's Set-Cookie headers.
    $promise = $client->request('http://www.google.com/');
    $response = wait($promise);

    // And this request will send the cookie returned in the first request.
    $promise = $client->request('http://www.google.com/');
    $response = wait($promise);
} catch (Amp\Artax\HttpException $e) {
    // If something goes wrong the Promise::wait() call will throw the relevant
    // exception. The Client::request() method itself will never throw.
    echo $e;
}
