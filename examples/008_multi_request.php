<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Note that Client::requestMulti() is nothing more than a convenience wrapper
 * to prevent us from having to call Client::request() several times and store
 * the resulting promises in an array ourselves. Doing so would have the exact
 * same effect and all requests would be executed in parallel either way.
 */
$promises = (new Amp\Artax\Client)->requestMulti([
    'google'    => 'http://www.google.com',
    'news'      => 'http://news.google.com',
    'bing'      => 'http://www.bing.com',
    'yahoo'     => 'https://www.yahoo.com',
]);

// Tolerate errors in some of the requests. If any one of the promises in our array
// succeeds then the result is a two-item array of errors and successes. If *none*
// of our response promises succeed then this line will throw.
list($errors, $responses) = Amp\some($promises)->wait();

// Alternatively we could use the following line to require all of our responses to succeed. If
// any one of the response promises resolves as a failure then this line will throw:
$responses = Amp\all($promises)->wait();

// Now, let's iterate over the responses to demonstrate that they retain the same keys from
// our original call to Amp\Artax\Client::request():
foreach ($responses as $key => $response) {
    printf(
        "%s | HTTP/%s %d %s\n",
        $key,
        $response->getProtocol(),
        $response->getStatus(),
        $response->getReason()
    );
}
