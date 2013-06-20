<?php

/**
 * The packaged `ProgressExtension` is an observable object that broadcasts a StdClass instance
 * summarizing the retrieval progress for a specific request. The extension broadcasts the
 * `ProgressExtension::PROGRESS` event with a two item array of data. The first item is the request
 * instance associated with the notification and the second is an `Artax\Ext\Progress\ProgressState`
 * object with the following public properties:
 * 
 *     socketReadyAt      - Timestamp when the client checked out a connected socket for this request
 *     redirectCount      - How many times the request has been redirected during retrieval
 *     bytesRcvd          - The total number of bytes received for the current request
 *     headerBytes        - The response headers size in bytes (if available, NULL until determined)
 *     contentLength      - The content length of the response message (if available, NULL otherwise)
 *     percentComplete    - Floating point value between 0 and 1
 *     bytesPerSecond     - Measures transfer speed
 *     progressBar        - A ready-to-echo progress bar display for the current request retrieval state
 * 
 * The `ProgressState` instance also contains a convenience method, `ProgressState::display()`, that
 * returns a summary display containing the progress bar, KB totals, completion percentage and download
 * speed.
 */

use Artax\Client,
    Artax\Request,
    Artax\Response,
    Artax\ClientException,
    Artax\Ext\Progress\ProgressExtension;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;
$ext = new ProgressExtension;
$ext->extend($client);

// --- Optional progress bar display config ---
$ext->setProgressBarSize(35);                   // defaults to 40, minimum of 10
$ext->setProgressBarIncrementChar('=');         // defaults to '='
$ext->setProgressBarEmptyIncrementChar('.');    // defaults to '.'
$ext->setProgressBarLeadingChar('>');           // defaults to '>'
// --- END optional progress bar config ---

$ext->subscribe([
    ProgressExtension::PROGRESS => function($dataArr) {
        $progress = $dataArr[1];
        echo $progress->display();
    }
]);

echo PHP_EOL;

try {
    $uri = 'http://en.wikipedia.org/wiki/Hitchhiker%27s_Guide_to_the_Galaxy';
    
    // Use HTTP/1.0 to prevent chunked encoding and hopefully receive a Content-Length header
    $request = (new Request)->setUri($uri)->setProtocol('1.0');
    $client->request($request);
    
} catch (ClientException $e) {
    echo $e->getMessage();
}

echo PHP_EOL, PHP_EOL;

