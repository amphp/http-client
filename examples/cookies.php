<?php

use Artax\Client,
    Artax\ClientException,
    Artax\Ext\Cookies\CookieExtension,
    Artax\Ext\Cookies\FileCookieJar;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

// Cookies will persist for the life of the client object
$ext = new CookieExtension;
$ext->subscribe($client);

// ---------------- If you wish to persist cookies beyond the life of the client ------------------>
// $cookieJar = new FileCookieJar('/hard/path/where/you/want/to/store/cookies.txt');
// $ext = new CookieExtension($cookieJar);
// $ext->subscribe($client);
// ------------------------------------------------------------------------------------------------>

try {
    $response = $client->request('http://www.google.com/');
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}

