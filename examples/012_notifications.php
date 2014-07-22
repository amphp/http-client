<?php // 012_notifications.php

require __DIR__ . '/../vendor/autoload.php';

/**
 * All Artax progress notifications take the form of an indexed array. The first element is a
 * notification code denoting the type of event being broadcast. The remaining items are data
 * associated with the event at hand.
 */
function notifyCallback(array $notifyData) {
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
 * Notifications when invoking BlockingClient::multiRequest are the same as those for individual
 * requests with the addition that the first parameter is the request key associated with the
 * event in question.
 */
function multiNotifyCallback($multiRequestKey, $notifyData) {
    echo $multiRequestKey, " | ", notifyCallback($notifyData);
}


/**
 * Blocking requests (Artax\BlockingClient) take a second callback parameter for notifications
 */
echo "\n==========================\nBLOCKING INDIVIDUAL REQUEST\n==========================\n\n";

$client = new Artax\BlockingClient;
try {
    $response = $client->request('http://www.google.com', 'notifyCallback');
} catch (Artax\ClientException $e) {
    echo $e;
}

echo "\n==========================\nBLOCKING MULTI REQUEST\n==========================\n\n";

$responses = $client->requestMulti([
    'google' => 'http://www.google.com',
    'news'   => 'http://news.google.com',
    'bing'   => 'http://www.bing.com',
], 'multiNotifyCallback');


/**
 * Non-blocking requests (Artax\Client) use the Promise::onProgress() function for notifications
 */
echo "\n==========================\nNON-BLOCKING ASYNC REQUEST\n==========================\n\n";

(new Alert\ReactorFactory)->select()->run(function($reactor) {
    $client = new Artax\Client($reactor);
    $promise = $client->request('http://www.google.com');
    $promise->onProgress('notifyCallback');
    $promise->onResolve(function($error = null, $response = null) use ($reactor) {
        $reactor->stop();
    });
});
