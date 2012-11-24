<?php

use Artax\Client,
    Artax\ClientBuilder,
    Spl\HashingMediator;

require dirname(__DIR__) . '/vendor/autoload.php';

define('CERT_AUTHORITY_FILE', dirname(__DIR__) . '/certs/cacert.pem');

define('STATE_CONNECTING', 'CONNECTING');
define('STATE_SENDING', 'SENDING');
define('STATE_RECEIVING', 'RECEIVING');
define('STATE_COMPLETE', 'COMPLETE');
define('STATE_ERROR', 'ERROR');

function display($requestInfo, $force = false) {
    static $lastDisplay;
    
    $now = microtime(true);
    $timeSinceLast = $now - $lastDisplay;
    if (!$force && $timeSinceLast < 0.12) {
        return;
    } else {
        $lastDisplay = $now;
    }
    
    // clear screen
    print chr(27) . "[2J" . chr(27) . "[;H";
    
    $str = PHP_EOL;
    $str.= str_pad('Key', 24) . str_pad('Size (KB)', 12) . str_pad('Redirects', 12);
    $str.= str_pad('Speed (KB/s)', 16) . str_pad('State', 16) . 'Response';
    $str.= PHP_EOL;
    
    foreach ($requestInfo as $key => $info) {
        $kbRecd = $info['bytesRecd'] ? number_format(round(($info['bytesRecd'] / 1024), 2), 2) : '-';
        $kbRecd = str_pad($kbRecd, 7, ' ', STR_PAD_LEFT);
        
        $redirs = $info['redirects'] ?: '-';
        $redirs = str_pad($redirs, 5, ' ', STR_PAD_LEFT);
        
        $speed = $info['avgSpeed'] ? number_format($info['avgSpeed'], 2) : '-';
        $speed = str_pad($speed, 8, ' ', STR_PAD_LEFT);
        
        $state = $info['state'];
        $response = $info['response'];
        
        $str.= str_pad($key, 24) . str_pad($kbRecd, 12) . str_pad($redirs, 12);
        $str.= str_pad($speed, 16) . str_pad($state, 16) . $response;
        $str.= PHP_EOL;
    }
    
    echo $str;
}

$requests = array(
    'www.facebook.com'          => 'http://www.facebook.com/',
    'www.bing.com'              => 'http://www.bing.com',
    'www.google.com'            => 'http://www.google.com',
    'www.yahoo.com'             => 'http://www.yahoo.com',
    'www.wikipedia.org'         => 'http://www.wikipedia.org',
    'www.espn.com'              => 'http://www.espn.com',
    'www.nytimes.com'           => 'http://www.nytimes.com',
    'www.php.net'               => 'http://www.php.net',
    'prototype.php.net'         => 'http://prototype.php.net',
    'www.youtube.com'           => 'http://www.youtube.com/',
    'www.baidu.com'             => 'http://www.baidu.com/',
    'twitter.com'               => 'http://twitter.com/',
    'www.amazon.com'            => 'http://www.amazon.com/',
    'www.yandex.ru'             => 'http://www.yandex.ru/',
    'www.google.co.jp'          => 'http://www.google.co.jp/',
    'so-home'                   => 'http://stackoverflow.com',
    'so-php'                    => 'http://stackoverflow.com/questions/tagged/php',
    'so-python'                 => 'http://stackoverflow.com/questions/tagged/python',
    'so-http'                   => 'http://stackoverflow.com/questions/tagged/http',
    'so-html'                   => 'http://stackoverflow.com/questions/tagged/html',
    'so-css'                    => 'http://stackoverflow.com/questions/tagged/css',
    'so-js'                     => 'http://stackoverflow.com/questions/tagged/javascript'
);

$info = array(
    'state' => STATE_CONNECTING,
    'bytesRecd' => 0,
    'avgSpeed' => null,
    'redirects' => 0,
    'response' => null
);
$requestInfo = array_map(function($r) use ($info) { return $info; }, $requests);



$mediator = new HashingMediator;

$mediator->addListener(Client::EVENT_READ, function($requestKey, $data, $bytes, $bodySize, $stats) use (&$requestInfo) {
    $requestInfo[$requestKey]['state'] = STATE_RECEIVING;
    $requestInfo[$requestKey]['bytesRecd'] += $bytes;
    $requestInfo[$requestKey]['avgSpeed'] = $stats['avgDownKbps'];
    display($requestInfo);
});
$mediator->addListener(Client::EVENT_WRITE, function($requestKey, $data, $bytes, $stats) use (&$requestInfo) {
    $requestInfo[$requestKey]['state'] = STATE_SENDING;
    display($requestInfo);
});
$mediator->addListener(Client::EVENT_REDIRECT, function($requestKey, $response, $stats) use (&$requestInfo) {
    ++$requestInfo[$requestKey]['redirects'];
    $requestInfo[$requestKey]['state'] = STATE_CONNECTING;
    $requestInfo[$requestKey]['bytesRecd'] = 0;
    $requestInfo[$requestKey]['avgSpeed'] = null;
    display($requestInfo);
});
$mediator->addListener(Client::EVENT_RESPONSE, function($requestKey, $response, $stats) use (&$requestInfo) {
    $requestInfo[$requestKey]['state'] = STATE_COMPLETE;
    $requestInfo[$requestKey]['response'] = $response->getStartLine();
    display($requestInfo);
});
$mediator->addListener(Client::EVENT_ERROR, function($requestKey, $e) use (&$responses) {
    $requestInfo[$requestKey]['state'] = STATE_ERROR;
    display($requestInfo);
});

$clientBuilder = new ClientBuilder;
$client = $clientBuilder->build($mediator);
$client->setAttribute(Client::ATTR_SSL_CA_FILE, CERT_AUTHORITY_FILE);

echo PHP_EOL;

$timeStart = microtime(true);
$multiResult = $client->sendMulti($requests);
$timeEnd = microtime(true);
$totalTime = round(($timeEnd - $timeStart), 4);

usleep(100);
display($requestInfo, true);