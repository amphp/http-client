<?php

// git clone --recursive git://github.com/rdlowrey/Artax.git
// php Artax/test/client-test.php


use Spl\HashingMediator,
    Artax\Http\Client,
    Artax\Http\ConnectionManager,
    Artax\Http\StdRequest;

require dirname(__DIR__) . '/Artax.php';


$mediator = new HashingMediator();
$connMgr  = new ConnectionManager($mediator);
$client   = new Client($connMgr, $mediator);

$requests = array(
    new StdRequest('http://stackoverflow.com', 'GET'),
    new StdRequest('http://stackoverflow.com/users/895378/rdlowrey', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/php', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/python', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/ajax', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/javascript', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/jquery', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/android', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/css', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/http', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/http', 'GET'),
    new StdRequest('http://stackoverflow.com/questions/tagged/xml', 'GET'),
    new StdRequest('http://www.google.com', 'GET'),
    new StdRequest('http://www.wikipedia.org', 'GET'),
    new StdRequest('http://www.nytimes.com', 'GET'),
    new StdRequest('http://www.espn.com', 'GET'),
    new StdRequest('http://prototype.php.net/', 'GET')
);




$aggregates = new StdClass;
$aggregates->bytesSent = 0;
$aggregates->bytesRecd = 0;
$aggregates->redirections = 0;;

$ioWriteListener = function($request, $data) use ($aggregates) {
    echo $data;
    $aggregates->bytesSent += strlen($data);
};
$ioReadBodyListener = function($request, $data) use ($aggregates) {
    $aggregates->bytesRecd += strlen($data);
};
$ioReadHeaderListener = function($request, $data) use ($aggregates) {
    $aggregates->bytesRecd += strlen($data);
};
$redirectListener = function($request, $data) use ($aggregates) {
    echo PHP_EOL . PHP_EOL . "~ $data" . PHP_EOL;
    ++$aggregates->redirections;
};
$responseListener = function($request, $response) use ($aggregates) {
    echo PHP_EOL . $request->getUri() . " :: " . $response->getStartLine() . PHP_EOL;
};
$connOpenListener = function($conn) use ($aggregates) {
    echo PHP_EOL . "+ Conn OPEN: $conn";
};
$connCloseListener = function($conn) use ($aggregates) {
    echo PHP_EOL . "+ Conn CLOSE: $conn";
};
$connCheckoutListener = function($conn) use ($aggregates) {
    echo PHP_EOL . "+ Conn CHECKOUT: $conn";
};
$connCheckinListener = function($conn) use ($aggregates) {
    echo PHP_EOL . "+ Conn CHECKIN: $conn";
};

$mediator->addListener(Client::EVENT_CONN_OPEN, $connOpenListener);
$mediator->addListener(Client::EVENT_CONN_CLOSE, $connCloseListener);
$mediator->addListener(Client::EVENT_CONN_CHECKOUT, $connCheckoutListener);
$mediator->addListener(Client::EVENT_CONN_CHECKIN, $connCheckinListener);
$mediator->addListener(Client::EVENT_IO_WRITE_HEADERS, $ioWriteListener);
$mediator->addListener(Client::EVENT_IO_WRITE_BODY, $ioWriteListener);
$mediator->addListener(Client::EVENT_IO_READ_HEADERS, $ioReadHeaderListener);
$mediator->addListener(Client::EVENT_IO_READ_BODY, $ioReadBodyListener);
$mediator->addListener(Client::EVENT_REDIRECT, $redirectListener);
//$mediator->addListener(Client::EVENT_RESPONSE_COMPLETE, $responseListener);




try {
    $timeStart = microtime(true);
    $multiResponse = $client->sendMulti($requests);
    $timeEnd = microtime(true);
} catch (Exception $e) {
    echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;
    echo "SUMMARY: Something broke and @rdlowrey is dumb :( ";
    echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;
    echo "php version: " . PHP_VERSION . PHP_EOL;
    echo "uname: " . php_uname() . PHP_EOL . PHP_EOL;
    
    echo $e . PHP_EOL . PHP_EOL;
    die;
}





// don't echo connection close data when the client object is destroyed
$mediator->removeListenersForEvent(Client::EVENT_CONN_CLOSE);

echo PHP_EOL . PHP_EOL . str_repeat('=', 80) . PHP_EOL . PHP_EOL;

foreach ($multiResponse as $key => $response) {
    echo str_pad($requests[$key]->getUri(), 55) . " :: ";
    echo $response->getStartLine() . PHP_EOL;
}

foreach ($multiResponse->getAllErrors() as $key => $exceptionObj) {
    echo str_pad($requests[$key]->getUri(), 55) . " :: ";
    echo $exceptionObj->getMessage() . PHP_EOL;
}

echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;
echo "SUMMARY: Woot! @rdlowrey is awesome :) ";
echo PHP_EOL . str_repeat('=', 80) . PHP_EOL;

echo "php version: " . PHP_VERSION . PHP_EOL;
echo "uname: " . php_uname() . PHP_EOL . PHP_EOL;

echo "Total requests: " . count($requests) . PHP_EOL;
echo "Total redirections: {$aggregates->redirections}" . PHP_EOL;
echo "Total bytes SENT: {$aggregates->bytesSent}" . PHP_EOL;
echo "Total bytes RECD: {$aggregates->bytesRecd}" . PHP_EOL;
echo 'Total time: ' . ($timeEnd - $timeStart) . ' seconds' . PHP_EOL;

echo PHP_EOL;



