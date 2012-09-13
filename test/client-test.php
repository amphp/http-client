<?php

use Spl\HashingMediator,
    Artax\Uri,
    Artax\Client,
    Artax\Network\Stream,
    Artax\Http\StdRequest,
    Artax\Http\Response;

require 'bootstrap.php';

$mediator = new HashingMediator();
$client = new Client($mediator);

$requests = array(
	new StdRequest('http://stackoverflow.com', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/php', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/python', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/http', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/html', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/css', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/java', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/javascript', 'GET'),
	new StdRequest('http://stackoverflow.com/questions/tagged/sql', 'GET'),
	new StdRequest('http://www.google.com', 'GET'),
	new StdRequest('http://www.yahoo.com', 'GET'),
    new StdRequest('http://wikipedia.org', 'GET'),
    new StdRequest('http://www.espn.com', 'GET'),
    new StdRequest('http://prototype.php.net/', 'GET')
);




$aggregates = new StdClass;

$aggregates->bytesSent = 0;
$aggregates->bytesRecd = 0;
$aggregates->redirections = 0;;

$ioWriteListener = function($key, $data, $bytes) use ($aggregates) {
    echo $data;
	$aggregates->bytesSent += $bytes;
};
$ioReadListener = function($key, $data, $bytes) use ($aggregates) {
    $aggregates->bytesRecd += $bytes;
};
$redirectListener = function($key, $oldLocation, $newLocation) use ($aggregates) {
    echo PHP_EOL . PHP_EOL . "~ REDIRECT $oldLocation ---> $newLocation" . PHP_EOL;
    ++$aggregates->redirections;
};
$errorListener = function($key, $exception) use ($aggregates, $requests) {
    echo $requests[$key]->getUri() . ' :: ' . $exception->getMessage() . PHP_EOL;
};
$responseListener = function($key, $response) use ($aggregates, $requests) {
    echo $requests[$key]->getUri() . ' :: ' . $response->getStartLine() . PHP_EOL;
};
$connOpenListener = function($streamObj) use ($aggregates) {
    echo '+ CONN OPEN: ' . $streamObj->getUri() . PHP_EOL;
};
$connCloseListener = function($streamObj) use ($aggregates) {
    echo '+ CONN CLOSE: ' . $streamObj->getUri() . PHP_EOL;
};
$connCheckoutListener = function($streamObj) use ($aggregates) {
    echo '+ CONN CHECKOUT ' . $streamObj->getUri() . PHP_EOL;
};
$connCheckinListener = function($streamObj) use ($aggregates) {
    echo '+ CONN CHECKIN: ' . $streamObj->getUri() . PHP_EOL;
};

$mediator->addListener(Stream::EVENT_OPEN, $connOpenListener);
$mediator->addListener(Stream::EVENT_CLOSE, $connCloseListener);
$mediator->addListener(Stream::EVENT_READ, $ioReadListener);
$mediator->addListener(Stream::EVENT_WRITE, $ioWriteListener);
$mediator->addListener(Client::EVENT_STREAM_CHECKOUT, $connCheckoutListener);
$mediator->addListener(Client::EVENT_STREAM_CHECKIN, $connCheckinListener);
$mediator->addListener(Client::EVENT_ERROR, $errorListener);
$mediator->addListener(Client::EVENT_REDIRECT, $redirectListener);
$mediator->addListener(Client::EVENT_RESPONSE, $responseListener);


try {
    $timeStart = microtime(true);
    $responses = $client->sendMulti($requests);
    $timeEnd = microtime(true);
} catch (Exception $e) {
    echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;
    echo "SUMMARY: Something broke :(";
    echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;
    echo "php version: " . PHP_VERSION . PHP_EOL;
    echo "uname: " . php_uname() . PHP_EOL . PHP_EOL;
    
    echo $e . PHP_EOL . PHP_EOL;
    die;
}

$mediator->removeListenersForEvent(Stream::EVENT_CLOSE);

echo PHP_EOL . PHP_EOL . str_repeat('=', 80) . PHP_EOL . PHP_EOL;

foreach ($responses as $key => $r) {
    echo str_pad($requests[$key]->getUri(), 55) . " :: ";
    echo $responses->isError() ? $r->getMessage() : $r->getStartLine() . PHP_EOL;
}

echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;
echo "SUMMARY: Woot! ";
echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;

echo "PHP " . PHP_VERSION . PHP_EOL;
echo php_uname() . PHP_EOL . PHP_EOL;

echo "Total requests: " . (count($requests) + $aggregates->redirections) . PHP_EOL;
echo "Total bytes SENT: {$aggregates->bytesSent}" . PHP_EOL;
echo "Total bytes RECD: {$aggregates->bytesRecd}" . PHP_EOL;
echo 'Total time: ' . ($timeEnd - $timeStart) . ' seconds' . PHP_EOL;

echo PHP_EOL;