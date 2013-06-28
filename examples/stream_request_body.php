<?php

/**
 * Artax accepts two request body data types: scalars and `Iterator` instances. Iterators are required
 * so that the client can adequately stream these bodies to remote servers. Built-in body iterators
 * include:
 * 
 * - Artax\FileBody      Streams a file as the request entity body
 * - Artax\ResourceBody  Streams any seekable stream resource as the request body
 * - Artax\FormBody      Provides an interface for constructing form submissions
 * 
 * The `FileBody` and `ResourceBody` classes are demonstrated below. `FormBody` usage is demonstrated
 * in the examples/forms.php file.
 * 
 * You can also specify your own custom iterator bodies. Custom iterator bodies should implement
 * `Countable` if at all possible. If an iterator body is countable, Artax will use its count()
 * return value to determine the Content-Length in the event of an HTTP/1.0 server that does not
 * accept chunked entity bodies. If a Content-Lenght header is needed and an Iterator body isn't
 * Countable, Artax will iterate until no longer valid to determine the length. Don't worry, though,
 * it won't buffer the full body in memory at any given time.
 * 
 * 
 */

require dirname(__DIR__) . '/autoload.php'; // <-- autoloader script

$client = new Artax\Client;

// Turn on verboseSend so we can watch the raw request message data as it's sent
$client->setOption('verboseSend', TRUE);



$body = new Artax\FileBody(__DIR__ . '/support/stream_body.txt');
$request = (new Artax\Request)->setUri('http://httpbin.org/post')->setMethod('POST')->setBody($body);

echo "\nArtax\FileBody: ----------------------------------------------------------------------\n\n";
try {
    $response = $client->request($request);
} catch (Artax\ClientException $e) {
    echo $e, "\n";
}



$stream = fopen('data://text/plain;base64,' . base64_encode(str_repeat('x', 256)), 'r');
$body = new Artax\ResourceBody($stream);
$request = (new Artax\Request)->setUri('http://httpbin.org/post')->setMethod('POST')->setBody($body);

echo "\nArtax\ResourceBody: ------------------------------------------------------------------\n\n";
try {
    $response = $client->request($request);
} catch (Artax\ClientException $e) {
    echo $e;
}



class MyStreamBody implements Iterator, Countable {
    
    private $count = 0;
    private $position = 0;
    private $parts = [
        '<html><body style="font-family: Sans-Serif;"><h1>Hello, world.</h1>',
        '<h2>Streaming Iterator chunk 1</h2>',
        '<h3>Streaming Iterator chunk 2</h3>',
        '<h4>Streaming Iterator chunk 3</h4>',
        '</body></html>'
    ];
    
    function __construct() {
        foreach ($this->parts as $part) {
            $this->count += strlen($part);
        }
    }
    function count() { return $this->count; }
    function rewind() { $this->position = 0; }
    function current() { return $this->parts[$this->position]; }
    function key() { return $this->position; }
    function next() { $this->position++; }
    function valid() { return array_key_exists($this->position, $this->parts); }
}

$body = new MyStreamBody;
$request = (new Artax\Request)->setUri('http://httpbin.org/post')->setMethod('POST')->setBody($body);

echo "\nMyStreamBody: ------------------------------------------------------------------------\n\n";

try {
    $response = $client->request($request);
} catch (Artax\ClientException $e) {
    echo $e;
}

echo PHP_EOL;

