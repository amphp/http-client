<?php

/**
 * Client/AsyncClient accept option assignment via setOption() and setAllOptions(). While the
 * defaults are usually fine, you can tweak any of the values presented below.
 */

use Artax\Client,
    Artax\ClientException;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

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

$client->setAllOptions([
    'useKeepAlive'          => TRUE,    // Use persistent connections (when the remote server allows it)
    'connectTimeout'        => 15,      // Timeout connect attempts after N seconds
    'transferTimeout'       => 30,      // Timeout transfers after N seconds
    'keepAliveTimeout'      => 30,      // How long to retain socket connections after a keep-alive request
    'followLocation'        => TRUE,    // Transparently follow redirects
    'autoReferer'           => TRUE,    // Automatically set the Referer header when following Location headers
    'maxConnections'        => -1,      // Max number of simultaneous sockets allowed (unlimited by default)
    'maxConnectionsPerHost' => 4,       // Max number of simultaneous sockets allowed per unique host
    'continueDelay'         => 3,       // How many seconds to wait for a 100 Continue response if `Expect: 100-continue` header used
    'bufferBody'            => TRUE,    // TRUE to buffer response bodies as strings, FALSE to keep them as temp streams
    'bindToIp'              => NULL,    // Optionally bind request sockets to a specific local IP on your machine
    'ioGranularity'         => 65536,   // Max bytes to read/write per socket IO operation
    'verboseRead'           => FALSE,   // If TRUE, send all raw message data received to STDOUT
    'verboseSend'           => FALSE    // If TRUE, send all raw message data written to STDOUT
]);
