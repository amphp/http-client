<?php // 005_persistent_cookies.php

require __DIR__ . '/../vendor/autoload.php';

$reactor = (new Alert\ReactorFactory)->select();
$cookieJar = new FileCookieJar('/path/to/my_cookies.txt');
$asyncClient = new Artax\Client($reactor, $cookieJar);
$client = new Artax\BlockingClient($reactor, $asyncClient);

// Enable verbose send so we can see our raw request messages in the console
// as they're sent to the server.
$client->setOption(Artax\Client::OP_VERBOSE, Artax\Client::VERBOSE_SEND);

try {
    // This request will receive and store google's Set-Cookie headers.
    $response = $client->request('http://www.google.com/');
    
    // And this request will send the cookie returned in the first request.
    // In your console you'll see that this second request contains a Cookie header.
    $response = $client->request('http://www.google.com/');

} catch (Artax\ClientException $e) {
    echo $e;
}
