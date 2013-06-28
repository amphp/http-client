<?php

/**
 * By enabling the Cookie Extension we can automatically store/send cookies as we retrieve resources
 * requested in Artax.
 */

use Artax\Client,
    Artax\ClientException,
    Artax\Ext\Cookies\CookieExtension,
    Artax\Ext\Cookies\FileCookieJar;

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Client;

// Enable verboseSend so we can see our raw request messages in the console
$client->setOption('verboseSend', TRUE);

// Cookies will persist for the life of the client object
(new CookieExtension)->extend($client);

// ---------------- If you wish to persist cookies beyond the life of the client ------------------>
// $cookieJar = new FileCookieJar('/hard/path/where/you/want/to/store/cookies.txt');
// $ext = new CookieExtension($cookieJar);
// $ext->extend($client);
// ------------------------------------------------------------------------------------------------>

try {
    // This request will receive and store google's `Set-Cookie` headers.
    $response = $client->request('http://www.google.com/');
    
    // And another request with the cookies applied. In your console you'll see that this second
    // request contains the `Cookie:` headers we set from the fist response.
    $response = $client->request('http://www.google.com/');
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    // Client::request() is the only Artax retrieval method that can throw. The others work in
    // parallel and instead notify error callbacks.
    
    echo $e;
}

