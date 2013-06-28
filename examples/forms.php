<?php

/**
 * Artax simplifies HTML form submissions. Simply use the built-in Artax\FormBody class to
 * construct your form values and you're finished. There's no need to understand the intricacies
 * of the multipart/form-data or application/x-www-form-urlencoded MIME types.
 * 
 * **IMPORTANT:** Note that any files you send as part of the form submission are *always* streamed
 * to minimize memory use. This is managed regardless of the HTTP protocol in use (1.0/1.1).
 */
 
require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$body = new Artax\FormBody;

$body->addField('field1', 'val1');
$body->addFileField('file1', dirname(__DIR__) . '/test/fixture/lorem.txt');
$body->addFileField('file2', dirname(__DIR__) . '/test/fixture/answer.txt');

// -------------------------------------------------------------------------------------------------

$client = new Artax\Client;
$request = (new Artax\Request)->setBody($body)->setUri('http://httpbin.org/post')->setMethod('POST');

try {
    $response = $client->request($request);
    
    // httbin.org sends us a JSON response summarizing our data
    echo $response->getBody();
    
} catch (Artax\ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    // Client::request() is the only Artax retrieval method that can throw. The others work in
    // parallel and instead notify error callbacks.
    
    echo $e;
}

