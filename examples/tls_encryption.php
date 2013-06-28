<?php

/**
 * For normal TLS-encrypted sites (https://) Artax will "just work" with the default settings. The
 * library uses the same certificate authority file as the Mozilla Foundation.
 * 
 * Artax uses PHP's SSL stream wrapper options to customize TLS connection settings. These options
 * may be modified as demonstrated here:
 * 
 *     $client->setOption('tlsOptions', [
 *         'verify_peer' => TRUE,
 *         'allow_self_signed' => FALSE,
 *         'cafile' => dirname(__DIR__) . '/certs/cacert.pem',
 *         'capath' => NULL,
 *         'local_cert' => NULL,
 *         'passphrase' => NULL,
 *         'CN_match' => NULL,
 *         'verify_depth' => NULL,
 *         'ciphers' => NULL,
 *         'SNI_enabled' => NULL,
 *         'SNI_server_name' => NULL
 *     ]);
 * 
 * Artax also ships with an executable file, bin/cert-updater.php that you can run periodically to
 * update your trusted certificate authorities. This file is generally updated every few months and
 * you should endeaver to keep yours current for maximum security.
 */

if (!extension_loaded('openssl')) {
    die("Cannot use TLS functionality without ext/openssl!\n");
}

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;

try {

    $response = $client->request('https://www.google.com/');// <-- note the https:// in our URI
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
    
} catch (Artax\ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    // Client::request() is the only Artax retrieval method that can throw. The others work in
    // parallel and instead notify error callbacks.
    
    echo $e;
}

