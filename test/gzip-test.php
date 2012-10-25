<?php

use Artax\Client,
    Artax\BroadcastClient,
    Artax\Http\StdRequest,
    Spl\HashingMediator;

require dirname(__DIR__) . '/vendor/autoload.php';

define('FAILURE_LOG_FILE', __DIR__.'/gzip-failure.log');

$requests = array(
    'stackoverflow.com' => new StdRequest('http://stackoverflow.com'),
    'www.facebook.com'  => new StdRequest('https://www.facebook.com/'),
    'www.youtube.com'   => new StdRequest('http://www.youtube.com/'),
    'www.baidu.com'     => new StdRequest('http://www.baidu.com/'),
    'twitter.com'       => new StdRequest('https://twitter.com/'),
    'www.amazon.com'    => new StdRequest('http://www.amazon.com/'),
    'www.yandex.ru'     => new StdRequest('http://www.yandex.ru/'),
    'www.google.co.jp'  => new StdRequest('http://www.google.co.jp/')
);


$decompressor = function ($response, $key) {
    if (!$response->hasHeader('Content-Encoding')
        || strcmp($response->getHeader('Content-Encoding'), 'gzip')
    ) {
        return;
    }
    
    $encodedStream = $response->getBodyStream();
    
    $headers = fread($encodedStream, 10);
    if (!preg_match(",^\x1f\x8b(?:\x08|\b),", $headers, $m)) {
        rewind($encodedStream);
        $response->addHeader('Warning', "299 Invalid gzip entity body; decompression failed");
        
        $log = fopen(FAILURE_LOG_FILE, 'w+');
        fwrite($log, $response->getRequestUri() . "\r\n-----------------------------------\r\n");
        fwrite($log, $response);
        
        die(
            "-------------------------------------------------------------------" . PHP_EOL . 
            "BAD GZIP!!! - Log file: " . FAILURE_LOG_FILE . PHP_EOL .
            "-------------------------------------------------------------------" . PHP_EOL .
            "Please post this log file online or email it to rdlowrey@gmail.com" . PHP_EOL
        );
        
        fclose($log);
    }
    
    $i = 10; 
    $flg = ord(substr($headers, 3, 1)); 
    
    if ($flg > 0) { 
        if ($flg & 4){ 
            list($xlen) = unpack('v', substr($headers, $i, 2)); 
            $i = $i + 2 + $xlen; 
        } 
        if ($flg & 8) {
            $i = strpos($headers, "\0", $i) + 1; 
        }
        if ($flg & 16) {
            $i = strpos($headers, "\0", $i) + 1; 
        }
        if ($flg & 2) {
            $i = $i + 2; 
        }
    }
    
    $finalStream = fopen('php://temp', 'r+');
    
    fseek($encodedStream, $i, SEEK_SET);
    stream_filter_append($encodedStream, 'zlib.inflate');
    stream_copy_to_stream($encodedStream, $finalStream);
    rewind($finalStream);
    
    $response->setBody($finalStream);
};


$mediator = new HashingMediator();
$mediator->addListener(BroadcastClient::EVENT_REQUEST, function($key, $request) {
    $request->setHeader('Accept-Encoding', 'gzip, identity');
});
$mediator->addListener(BroadcastClient::EVENT_RESPONSE, function($key, $response) use ($decompressor) {
    $decompressor($response, $key);
});
$mediator->addListener(BroadcastClient::EVENT_RESPONSE, function($key, $response, $info) {
    echo 'RESPONSE COMPLETE: ' . $response->getRequestUri() .' -- (' . $info['avgDownKbPerSecond'] . ' KB/s)';
    echo  PHP_EOL . $response->getStartLine() . PHP_EOL . PHP_EOL;
});

$client = new BroadcastClient($mediator);
$client->setAttribute(Client::ATTR_SSL_CA_FILE, dirname(__DIR__) .'/certs/cacert.pem');
$client->setAttribute(Client::ATTR_CONNECT_TIMEOUT, 5);

$multiResponse = $client->sendMulti($requests);