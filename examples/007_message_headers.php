<?php

require __DIR__ . '/../vendor/autoload.php';

$request = new Amp\Artax\Request("http://example.com/");

/**
 * **IMPORTANT:** All of the following examples apply to Amp\Artax\Request *and* Amp\Artax\Response.
 *
 * Headers are stored with case-insensitive keys (as per RFC 2616 Sec4.2). You can access and
 * assign message headers in requests/responses without worrying about case. Assigning headers is
 * accomplished using withHeader() which will clear any previously assigned values for the
 * same header (regardless of field case):
 */
$request = $request->withHeader('Content-Type', 'application/octet-stream');

assert($request->hasHeader('CONTENT-TYPE')); // true
assert($request->hasHeader('CoNtEnT-tYpE')); // true
assert($request->hasHeader('content-type')); // true
assert($request->getHeader('conTENT-tyPE')[0] === 'application/octet-stream'); // true

$request = $request->withHeader('CONTENT-TYPE', 'text/plain');

assert($request->getHeader('Content-Type')[0] === 'text/plain'); // true

/**
 * You can assign multiple header lines by passing an array of scalar values as the header value.
 * When sent by Amp\Artax the relevant portion of the raw request message for the below set of headers
 * will look like this:
 *
 * Foo: test=val1
 * Foo: test=val2
 * Foo: test=val3
 */
$request = $request->setHeader('Foo', ['test=val1', 'test=val2', 'test=val3']);

/**
 * Append headers without overwriting using withAddedHeader():
 */
assert(count($request->getHeaderArray('foo')) === 3); // true
$request = $request->withAddedHeader('Cookie', 'cookie4=val4');
assert(count($request->getHeaderArray('foo')) === 4); // true

/**
 * You can remove a previously assigned header value using Message::removeHeader(). Once again,
 * the header field name is case-insensitive:
 */

$request = $request->withoutHeader('cookie');
assert(!$request->hasHeader('Cookie')); // true

/**
 * If you attempt to retrieve a non-existent header Amp\Artax will return null:
 */

assert($request->getHeader('Some-Header-That-Isnt-Assigned') === null); // true