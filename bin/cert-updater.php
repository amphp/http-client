<?php

/**
 * Certificate Authority Updater
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
 *     $ php cert-updater.php --certdir=/hard/path/to/artax/certs -f
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
 *     $ php cert-updater.php
 */

use Artax\Client,
    Artax\ClientException,
    Artax\Request,
    Artax\Ext\Progress\ProgressExtension;

require dirname(__DIR__) . '/autoload.php';

define('REMOTE_CERT_URI', 'http://curl.haxx.se/ca/cacert.pem');
define('DEFAULT_CERT_PATH', dirname(__DIR__) . '/certs');
define('CERT_NAME', 'cacert.pem');
define('CACHED_ETAG', '.cacert-etag');

define('E_CERT_WRITE_FAILURE', 1);
define('E_REMOTE_SERVER_ERROR', 3);
define('E_UNEXPECTED_HTTP_RESPONSE', 4);
define('E_EXCEPTION', 5);

// ------------------------------------------------------------------------------------------------>

$options = getopt('f', array('certdir::'));

$forceOverwrite = isset($options['f']);

if (isset($options['certdir'])) {
    $certPath = $options['certdir'];
} else {
    echo PHP_EOL, 'Enter certificate directory (press Enter for default): ';
    $certPath = fgets(STDIN);
}

$certPath = trim($certPath);
$certPath = ($certPath === '') ? DEFAULT_CERT_PATH : $certPath;

$isDir = is_dir($certPath);

if ($isDir && !is_writable($certPath)) {
    echo "Cannot continue; the specified directory is not writable: $certPath", PHP_EOL;
    exit(E_CERT_WRITE_FAILURE);
} elseif (!$isDir) {
    echo "The specified directory does not exist; attempting to create it ...";
    
    if (!@mkdir($certPath)) {
        echo "Failed creating directory: $certPath", PHP_EOL;
        exit(E_CERT_WRITE_FAILURE);
    }
}

$caFile = $certPath . '/' . CERT_NAME;

$request = (new Request)->setUri(REMOTE_CERT_URI);

if (!$forceOverwrite) {
    $cachedEtagFile = $certPath . '/' . CACHED_ETAG;
    if (file_exists($caFile) && is_readable($cachedEtagFile)) {
        if ($etag = @file_get_contents($cachedEtagFile)) {
            $request->setHeader('If-None-Match', $etag);
        }
    }
}

$client = new Client;
(new ProgressExtension)->extend($client)->subscribe([
    ProgressExtension::PROGRESS => function($dataArr) {
        $progress = $dataArr[1];
        if (isset($progress->headerBytes)) {
            echo $progress->display();
        }
    }
]);

echo "Retrieving remote CA file: ", REMOTE_CERT_URI, PHP_EOL;

try {
    $response = $client->request($request);
} catch (ClientException $e) {
    echo $e, PHP_EOL;
    exit(E_EXCEPTION);
}

// ------------------------------------------------------------------------------------------------>

function getStartLine($response) {
    $startLine = 'HTTP/' . $response->getProtocol() . ' ';
    $startLine.= $response->getStatus() . ' ';
    $startLine.= $response->getReason();
    
    return $startLine;
}

// ------------------------------------------------------------------------------------------------>

$statusCode = $response->getStatus();

if ($statusCode == 304) {
    echo PHP_EOL, 'Certificate authority file already up to date!', PHP_EOL;
    exit(0);
    
} elseif ($statusCode == 200) {
    if ($response->hasHeader('Etag')) {
        if ($fh = @fopen($cachedEtagFile, 'w+')) {
            @fwrite($fh, current($response->getHeader('Etag')));
            @fclose($fh);
        }
    }
    
    if (!$fh = @fopen($caFile, 'w+')) {
        echo PHP_EOL, "Failed opening file for writing: $caFile", PHP_EOL;
        exit(E_CERT_WRITE_FAILURE);
    }
    
    if (!@fwrite($fh, $response->getBody())) {
        echo PHP_EOL, "Failed writing file: $caFile", PHP_EOL;
        exit(E_CERT_WRITE_FAILURE);
    }
    
    echo PHP_EOL, "Certificate authority file updated!", PHP_EOL;
    
    @fclose($fh);
    exit(0);
    
} elseif ($statusCode >= 500) {
    echo PHP_EOL, "Remote server error: ", getStartLine($response), PHP_EOL;
    exit(E_REMOTE_SERVER_ERROR);
    
} elseif ($statusCode >= 400) {
    echo PHP_EOL . "Unexpected error received: ", getStartLine($response), PHP_EOL;
    exit(E_UNEXPECTED_HTTP_RESPONSE);
    
}

