<?php

require __DIR__ . '/../vendor/autoload.php';

$request = new Amp\Artax\Request;



/**
 * **IMPORTANT:** All of the following examples apply to Amp\Artax\Request *and* Amp\Artax\Response.
 *
 * Headers are stored with case-insensitive keys (as per RFC 2616 Sec4.2). You can access and
 * assign message headers in requests/responses without worrying about case. Assigning headers is
 * accomplished using Message::setHeader() which will clear any previously assigned values for the
 * same header (regardless of field case):
 */
$request->setHeader('Content-Type', 'application/octet-stream');

assert($request->hasHeader('CONTENT-TYPE')); // true
assert($request->hasHeader('CoNtEnT-tYpE')); // true
assert($request->hasHeader('content-type')); // true
assert($request->getHeader('conTENT-tyPE')[0] === 'application/octet-stream'); // true

$request->setHeader('CONTENT-TYPE', 'text/plain');
assert($request->getHeader('Content-Type')[0] === 'text/plain'); // true



/**
 * You can assign multiple header lines by passing an array of scalar values as the header value.
 * When sent by Amp\Artax the relevant portion of the raw request message for the below set of headers
 * will look like this:
 *
 * Cookie: cookie1=val1
 * Cookie: cookie2=val2
 * Cookie: cookie3=val3
 */
$request->setHeader('Cookie', ['cookie1=val1', 'cookie2=val2', 'cookie3=val3']);



/**
 * Append headers without overwriting using Message::appendHeader():
 */
assert(count($request->getHeader('cookie')) === 3); // true
$request->appendHeader('Cookie', 'cookie4=val4');
assert(count($request->getHeader('Cookie')) === 4); // true



/**
 * You may set multiple headers at one time via $request->setAllHeaders():
 */
$request->setAllHeaders([
    'X-My-Header' => 'some value',
    'Accept' => '*/*',
    'Cookie' => [
        'cookie1=val1',
        'cookie1=val2',
    ]
]);



/**
 * You can remove a previously assigned header value using Message::removeHeader(). Once again,
 * the header field name is case-insensitive:
 */
$request->removeHeader('cookie');
assert( ! $request->hasHeader('Cookie')); // true



/**
 * If you attempt to retrieve a non-existent header Amp\Artax will throw a DomainException:
 */
try {
    $request->getHeader('Some-Header-That-Isnt-Assigned');
    die('Expected exception not thrown');
} catch (DomainException $e) {
    // Yep, it worked correctly.
}



/**
 * Most HTTP headers may appear multiple times in the same message. For this reason it makes sense
 * to represent all message headers as arrays. When you retrieve a header it will be returned as a
 * numerically-indexed array:
 */
$contentType = $request->getHeader('content-type');
assert(is_array($contentType)); // true
assert($contentType[0] === 'text/plain'); // true



/**
 * As a result, if you're interested in header fields for which there *should* only exist a single
 * value you should access them in this manner:
 */
if ($request->hasHeader('CONTENT-TYPE')) {
    $contentType = current($request->getHeader('Content-TYPE'));
    assert($contentType === 'text/plain');
}



/**
 * Finally, you can clear all headers from a message in one fell swoop:
 */
$request->removeAllHeaders();
assert( ! $request->getAllHeaders());
