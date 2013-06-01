<?php

use Artax\Client,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;
$uri = "https://www.google.com/"; // <-- note the https://

// We can customize our TLS environment in the following manner, but for most use-cases the default
// settings will "just work." The default values are reflected below:

/*
$client->setOption('tlsOptions', [
    'verify_peer' => TRUE,
    'allow_self_signed' => FALSE,
    'cafile' => dirname(__DIR__) . '/certs/cacert.pem',
    'capath' => NULL,
    'local_cert' => NULL,
    'passphrase' => NULL,
    'CN_match' => NULL,
    'verify_depth' => NULL,
    'ciphers' => NULL,
    'SNI_enabled' => NULL,
    'SNI_server_name' => NULL
]);
*/

try {
    $response = $client->request($uri);
    echo 'HTTP/' , $response->getProtocol() , ' ' , $response->getStatus() , ' ' , $response->getReason() , "\n";
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e->getMessage(), "\n";
}
