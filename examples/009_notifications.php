<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * All Amp\Artax progress notifications take the form of an indexed array. The first element is a
 * notification code denoting the type of event being broadcast. The remaining items are data
 * associated with the event at hand.
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
 * Progress updates are distributed by the promise. To "listen" for update notifications simply
 * pass a callback to Promise::watch() as demonstrated below. Note that these calls are chainable
 * and we could have written the following code in a single line:
 *
 * $response = (new Amp\Artax\Client)->request('http://www.google.com')->watch('myNotifyCallback')->wait();
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
