<?php

/**
 * This demo is more to execute than to read. Run this script in the console and awesomeness ensues.
 * The script downloads several HTTP resources in parallel while updating the console with progress
 * displays for each request in real time.
 */

use Artax\Client,
    Artax\Request,
    Artax\ClientException,
    Artax\Ext\Progress\ProgressExtension,
    Artax\Ext\Progress\ProgressDisplay;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

// --- Modify all requests for our specific use-case ---------------------------------------------->

$client->subscribe([
    Client::REQUEST => function($dataArr) {
        $request = $dataArr[0];
        // Use HTTP/1.0 to prevent chunked encoding and hopefully receive a Content-Length header.
        // Since we're using 1.0 we want to explicitly ask for keep-alives to avoid closing the
        // connection after each request.
        $request->setProtocol('1.0')->setHeader('Connection', 'keep-alive');
    }
]);

// --- Prep the request list ---------------------------------------------------------------------->

$requests = [
    'so-home'   => (new Request)->setUri('http://stackoverflow.com'),
    'so-php'    => (new Request)->setUri('http://stackoverflow.com/questions/tagged/php'),
    'so-python' => (new Request)->setUri('http://stackoverflow.com/questions/tagged/python'),
    'so-http'   => (new Request)->setUri('http://stackoverflow.com/questions/tagged/http'),
    'so-html'   => (new Request)->setUri('http://stackoverflow.com/questions/tagged/html'),
    'so-css'    => (new Request)->setUri('http://stackoverflow.com/questions/tagged/css'),
    'so-js'     => (new Request)->setUri('http://stackoverflow.com/questions/tagged/javascript'),
    'google'    => (new Request)->setUri('http://www.google.com'),
    'bing'      => (new Request)->setUri('http://www.bing.com'),
    'yahoo'     => (new Request)->setUri('http://www.yahoo.com'),
    'nytimes'   => (new Request)->setUri('http://www.nytimes.com'),
    'wikipedia' => (new Request)->setUri('http://en.wikipedia.org/wiki/Main_Page')
];

$lastUpdate = microtime(TRUE);
$displayLines = [];
$requestNameMap = new SplObjectStorage;

foreach ($requests as $requestKey => $request) {
    $requestNameMap->attach($request, $requestKey);
    $displayLines[$requestKey] = str_pad($requestKey, 20) . 'Awaiting connection ...';
}

// --- A function we'll call to update the console display ---------------------------------------->

function updateDisplay(array $displayLines) {
    print chr(27) . "[2J" . chr(27) . "[;H"; // clear screen
    echo '------------------------------------', PHP_EOL;
    echo 'Artax parallel request progress demo', PHP_EOL;
    echo '------------------------------------', PHP_EOL, PHP_EOL;
    
    echo implode($displayLines, PHP_EOL), PHP_EOL;
}

// --- Subscribe to progress updates -------------------------------------------------------------->

$ext = new ProgressExtension;
$ext->extend($client);
$ext->setProgressBarSize(30);
$ext->subscribe([
    ProgressExtension::PROGRESS => function($dataArr) use ($requestNameMap, &$displayLines, &$lastUpdate) {
        $now = microtime(TRUE);
        if (($now - $lastUpdate) > 0.05) { // Limit updates to 20fps to avoid a choppy display
            list($request, $progress) = $dataArr;
            $requestKey = $requestNameMap->offsetGet($request);
            $displayLines[$requestKey] = str_pad($requestKey, 15) . ProgressDisplay::display($progress);
            $lastUpdate = $now;
            updateDisplay($displayLines);
        }
    },
    ProgressExtension::RESPONSE => function($dataArr) use ($requestNameMap, &$displayLines) {
        list($request, $progress) = $dataArr;
        $requestKey = $requestNameMap->offsetGet($request);
        $displayLines[$requestKey] = str_pad($requestKey, 15) . ProgressDisplay::display($progress);
        updateDisplay($displayLines);
    },
    ProgressExtension::ERROR => function($dataArr) use (&$displayLines, $requestNameMap) {
        list($request, $progress, $error) = $dataArr;
        $requestKey = $requestNameMap->offsetGet($request);
        $displayLines[$requestKey] = str_pad($requestKey, 15) . 'Error: ' . $error->getMessage();
        updateDisplay($displayLines);
    }
    
]);

// --- Release the hounds! ------------------------------------------------------------------------>

$dummyOnResponse = $dummyOnError = function(){};
$client->requestMulti($requests, $dummyOnResponse, $dummyOnError);
echo PHP_EOL;

