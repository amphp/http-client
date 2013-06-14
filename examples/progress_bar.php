<?php

/**
 * Clients emit several events to which subscribers may listen:
 * 
 * Client::DATA         [$request, $socketDataRead]
 * Client::SEND         [$request, $socketDataSent]
 * Client::SOCKET       [$request, NULL]
 * Client::CANCEL       [$request, NULL]
 * Client::REQUEST      [$request, NULL]
 * Client::HEADERS      [$request, $parsedResponseArray]
 * Client::REDIRECT     [$request, NULL]
 * Client::RESPONSE     [$request, $response]
 * 
 * The data parameter for event broadcasts is always a two-element array. The first element is ALWAYS
 * the request responsible for the event. The second element is data pertinent to the event.
 * 
 * This example demonstrates how these events can be used to track the size and speed of an HTTP
 * transfer as it's happening to generate a progress display in the console.
 */

use Artax\Client,
    Artax\Request,
    Artax\Response,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

define('PROGRESS_BAR_WIDTH', 60);

function progress($bytesRcvd, $total, $barSize) {
    $kb = number_format(round($bytesRcvd / 1024, 2));
    if (isset($total)) {
        $percentage = round($bytesRcvd / $total, 2);
        $maxDashes = $barSize - 2;
        $displayDashes = round($percentage * $maxDashes);
        $emptyDashes = $maxDashes - $displayDashes;
        echo "\r[", str_repeat('=', $displayDashes), str_repeat('.', $emptyDashes), "] ", $percentage * 100, "% (", $kb, " KB)";
    } else {
        echo "\r[ --- ", $kb, " KB of UNKNOWN --- ]";
    }
}

// ------------------------------------------------------------------------------------------------>

$bytesRcvd = 0;
$contentLength = NULL;
$socketReadyAt = NULL;

// Capture the timestamp when the socket connection is established so we can calculate download speed
$onSocket = function(array $dataArr) use (&$socketReadyAt) {
    $socketReadyAt = microtime(TRUE);
};

// Output some info when the request starts up
$onRequest = function(array $dataArr) {
    $request = current($dataArr);
    $uri = $request->getUri();
    echo PHP_EOL, 'Retrieving: ', $uri, ' ...', PHP_EOL, PHP_EOL;
    echo "\rAwaiting connection to ", parse_url($uri, PHP_URL_HOST);
};

// Update the display each time we receive data on the socket
$onData = function(array $dataArr) use (&$bytesRcvd, &$contentLength, &$socketReadyAt) {
    $bytesRcvd += strlen($dataArr[1]);
    $elapsedTime = microtime(TRUE) - $socketReadyAt;
    $kbps = number_format(round(($bytesRcvd / $elapsedTime) / 1024, 2));
    
    echo progress($bytesRcvd, $contentLength, PROGRESS_BAR_WIDTH), " @ ", $kbps, " KB/s";
};

// Determine how big the message is (Content-Length) so we can determine progress as we go
$onHeaders = function(array $dataArr) use (&$contentLength) {
    $parsedResponseArr = array_pop($dataArr);
    $response = (new Response)->setAllHeaders($parsedResponseArr['headers']);
    $contentLength = $response->hasHeader('Content-Length')
        ? current($response->getHeader('Content-Length')) + strlen($parsedResponseArr['trace'])
        : NULL;
};

// ------------------------------------------------------------------------------------------------>

$client = new Client;
$client->subscribe([
    Client::SOCKET => $onSocket,
    Client::REQUEST => $onRequest,
    Client::DATA => $onData,
    Client::HEADERS => $onHeaders
]);

try {
    $uri = 'http://en.wikipedia.org/wiki/Hitchhiker%27s_Guide_to_the_Galaxy';
    // Use HTTP/1.0 to prevent chunked encoding (we want to get a Content-Length header back)
    $request = (new Request)->setUri($uri)->setProtocol('1.0');
    $client->request($request);
} catch (ClientException $e) {
    echo $e->getMessage();
}

echo PHP_EOL, PHP_EOL;

