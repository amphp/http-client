<?php

/**
 * Certificate Authority Updater
 * 
 * Artax is configured for maximum SSL transport security by default. This means that peer
 * certificates must be verified when making secure (https://) connections. If no certificate
 * authority attribute is specified, connections to secure URIs will fail. While secure peer 
 * verification may be disabled, it's preferable to specify a valid certificate authority file
 * instead:
 * 
 * ```php
 * $client->setAttribute(Artax\Client::ATTR_SSL_CA_FILE, $hardPathToPemFile);
 * ```
 * 
 * Artax ships with the latest CA certificate bundle from http://curl.haxx.se/ca/cacert.pem. This 
 * file is itself pulled directly from mozilla.org and is subject to the licensing constraints from 
 * the mozilla source code, namely: MPL 1.1, GPL v2.0 or LGPL 2.1. The mozilla certificate file is
 * updated periodically (usually a handful of times each year). This script helps ensure the 
 * packaged certificate authority file (certs/cacert.pem) is up-to-date.
 * 
 * 
 * Automated Usage
 * =================================================================================================
 * It's much easier to stay up-to-date when you don't have to remember anything. By specifying the
 * "certdir" command-line argument you can periodically update your certificate authority file
 * with a task scheduler like CRON:
 * 
 * ```
 * $ php cert-update.php -f --certdir=/hard/path/to/artax/certs
 * ```
 * 
 * Additionally, the "-f" option will force an overwrite of an existing certificate authority file
 * even if the existing file is already current.
 * 
 * 
 * Manual Usage
 * =================================================================================================
 * If you execute the script without the "certdir" command-line argument, you'll be asked to 
 * specify the certificate directory in which to store the .PEM file (or accept the default). To
 * manually update your certificate file:
 * 
 * ```
 * $ php cert-update.php
 * ```
 */

require __DIR__ . '/vendor/autoload.php';

define('REMOTE_CERT_URI', 'http://curl.haxx.se/ca/cacert.pem');
define('DEFAULT_CERT_PATH', __DIR__ . '/certs');
define('CERT_NAME', 'cacert.pem');
define('CACHED_ETAG', '.cacert-etag');
define('E_CERT_WRITE_FAILURE', 1);
define('E_REMOTE_SERVER_ERROR', 3);
define('E_UNEXPECTED_HTTP_RESPONSE', 4);
define('E_EXCEPTION', 5);


function getProgress($part, $whole, $width) {
    $width = $width - 2;
    
    $percentage = ceil(round(($part/$whole), 2) * 100);
    $dotCount = ceil($width * ($percentage / 100));
    
    $dotChar = '=';
    $result = '[' . str_repeat($dotChar, $dotCount);
    $result = $percentage < 100 ? (substr($result, 0, -1) . '>') : $result;
    $result = str_pad($result, $width);
    $result.= "] $percentage%";
    
    return $result;
}




$options = getopt('f', array('certdir::'));
$forceOverwrite = isset($options['f']);

if (isset($options['certdir'])) {
    $certPath = $options['certdir'];
} else {
    echo PHP_EOL . 'Enter certificate directory (press Enter for "./certs"): ';
    $certPath = fgets(STDIN);
}

$certPath = trim($certPath);
$certPath = ($certPath === '') ? DEFAULT_CERT_PATH : $certPath;

$isDir = is_dir($certPath);
if ($isDir && !is_writable($certPath)) {
    echo "Cannot continue; the specified directory is not writable: $certPath" . PHP_EOL;
    exit(E_CERT_WRITE_FAILURE);
} elseif (!$isDir) {
    echo "The specified directory does not exist; attempting to create it ...";
    
    if (!mkdir($certPath)) {
        echo "Failed creating directory: $certPath" . PHP_EOL;
        exit(E_CERT_WRITE_FAILURE);
    }
}

$totalBytesRead = 0;
$headerSize = 0;
$mediator = new Ardent\HashingMediator;
$mediator->addListener(Artax\Client::EVENT_READ,
    function($requestKey, $data, $bytes, $bodyBytes, $info) use (&$totalBytesRead, &$headerSize) {
        $totalBytesRead += $bytes;
        
        if (!$headerSize && $bodyBytes) {
            $headerSize = $totalBytesRead;
            echo PHP_EOL;
        }
        
        if ($headerSize) {
            $bodyBytesRecd = $totalBytesRead - $headerSize;
            $kbps = $info['avgDownKbps'];
            $progress = getProgress($bodyBytesRecd, $bodyBytes, 60) . " ($kbps KB/s)";
            echo "\r$progress\r";
            if ($bodyBytesRecd == $bodyBytes) {
                echo PHP_EOL;
            }
        }
    }
);

$clientBuilder = new Artax\ClientBuilder();
$client = $clientBuilder->build($mediator);
$request = new Artax\Http\StdRequest();

$request->setProtocol(1.1);
$request->setMethod('GET');
$request->setUri(REMOTE_CERT_URI);

$caFile = $certPath . '/' . CERT_NAME;

if (!$forceOverwrite) {
    $cachedEtagFile = $certPath . '/' . CACHED_ETAG;
    if (file_exists($caFile) && is_readable($cachedEtagFile)) {
        if ($etag = @file_get_contents($cachedEtagFile)) {
            $request->setHeader('If-None-Match', $etag);
        }
    }
}

echo "Retrieving remote CA file ..." . PHP_EOL;

try {
    $response = $client->send($request);
} catch (Exception $e) {
    echo PHP_EOL . $e . PHP_EOL;
    exit(6);
}

$statusCode = $response->getStatusCode();

if ($statusCode == 304) {
    echo PHP_EOL . 'Certificate authority file already up to date!' . PHP_EOL;
    exit(0);
    
} elseif ($statusCode == 200) {
    if ($response->hasHeader('Etag')) {
        if ($fh = @fopen($cachedEtagFile, 'w+')) {
            @fwrite($fh, $response->getCombinedHeader('Etag'));
            @fclose($fh);
        }
    }
    
    if (!$fh = @fopen($caFile, 'w+')) {
        echo PHP_EOL . "Failed opening file for writing: $caFile" . PHP_EOL;
        exit(E_CERT_WRITE_FAILURE);
    }
    
    if (!@fwrite($fh, $response->getBody())) {
        echo PHP_EOL . "Failed writing file: $caFile" . PHP_EOL;
        exit(E_CERT_WRITE_FAILURE);
    }
    
    $stats = $client->getRequestStats();
    $totalKb = round(($stats['bytesRecd'] / 1024), 2);
    
    echo PHP_EOL . "Certificate authority file updated! ($totalKb KB received)" . PHP_EOL;
    
    @fclose($fh);
    exit(0);
    
} elseif ($statusCode >= 500) {
    echo PHP_EOL . "Remote server encountered an error: " . $response->getStartLine() . PHP_EOL;
    exit(E_REMOTE_SERVER_ERROR);
    
} elseif ($statusCode >= 400) {
    echo PHP_EOL . "Unexpected error status received: " . $response->getStartLine() . PHP_EOL;
    exit(E_UNEXPECTED_HTTP_RESPONSE);
    
}