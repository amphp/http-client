<?php

use Artax\Client,
    Artax\ClientException,
    Artax\Ext\Cookies\CookieExtension,
    Artax\Ext\Cookies\FileCookieJar;

require dirname(__DIR__) . '/autoload.php';

$client = new Client;

// Cookies will persist for the life of the client object
(new CookieExtension)->extend($client);

// ---------------- If you wish to persist cookies beyond the life of the client ------------------>
// $cookieJar = new FileCookieJar('/hard/path/where/you/want/to/store/cookies.txt');
// $ext = new CookieExtension($cookieJar);
// $ext->extend($client);
// ------------------------------------------------------------------------------------------------>

try {
    // One request to receive and store the `Set-Cookie` headers
    $response = $client->request('http://www.google.com/');
    
    // Here we register an event listener so we can see the stored `Cookie` headers being sent when
    // the second request is made:
    $client->subscribe([
        Client::REQUEST => function(array $dataArr) {
            $request = current($dataArr);
            foreach ($request->getHeader('Cookie') as $cookieHeader) {
                echo 'Cookie: ', $cookieHeader, PHP_EOL;
            }
        }
    ]);
    
    // And another request with cookies
    $response = $client->request('http://www.google.com/');
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}

