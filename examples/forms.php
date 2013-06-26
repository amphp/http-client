<?php

/**
 * Artax makes submitting HTML forms dead-simple. Simply use the built-in Artax\FormBody class to
 * construct your form values and you're finished. There's no need to understand the intricacies
 * of the multipart/form-data or application/x-www-form-urlencoded MIME types.
 * 
 * **IMPORTANT:** Note that any files you send as part of the form submission are *always* streamed
 * to minimize memory use. This is managed regardless of the HTTP protocol in use (1.0/1.1).
 */
 
use Artax\Client,
    Artax\ClientException,
    Artax\FormBody,
    Artax\Request;

require __DIR__ . '/autoload.php';


$body = new FormBody;

$body->addField('field1', 'val1');
$body->addFileField('file1', __DIR__ . '/test/fixture/lorem.txt');
$body->addFileField('file2', __DIR__ . '/test/fixture/answer.txt');

// -------------------------------------------------------------------------------------------------

$client = new Client;
$request = (new Request)->setBody($body)->setUri('http://httpbin.org/post')->setMethod('POST');

try {
    $response = $client->request($request);
    echo $response->getBody(); // <--- outputs a JSON response from httbin.org summarizing what we sent.
    
} catch (ClientException $e) {
    // Connection failed, socket died or an unparsable response message was returned
    echo $e;
}

