<?php

use Amp\Artax\Client;
use Amp\Artax\Response;
use Amp\File\Handle;
use Amp\File\StatCache;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

Loop::run(function () {
    try {
        $start = microtime(1);

        // Instantiate the HTTP client
        $client = new Amp\Artax\DefaultClient;

        // Make an asynchronous HTTP request
        $promise = $client->request('http://speed.hetzner.de/100MB.bin', [
            Client::OP_MAX_BODY_BYTES => 120 * 1024 * 1024
        ]);

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        /** @var Response $response */
        $response = yield $promise;

        // Output the results
        printf(
            "HTTP/%s %d %s\n%s\n\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason(),
            $response->getRequest()->getUri()
        );

        foreach ($response->getHeaders() as $field => $values) {
            foreach ($values as $value) {
                print "$field: $value\n";
            }
        }

        print "\n";

        $path = tempnam(sys_get_temp_dir(), "artax-streaming-");

        /** @var Handle $file */
        $file = yield Amp\File\open($path, "w");

        // The response body is an instance of Message, which allows buffering or streaming by the consumers choice.
        // Pipe streams the body into the file, which is an instance of OutputStream.
        yield Amp\ByteStream\pipe($response->getBody(), $file);
        yield $file->close();

        print sprintf(
            "Done in %.2f with peak memory usage of %.2fMB.\n",
            microtime(1) - $start,
            (float) memory_get_peak_usage(true) / 1024 / 1024
        );

        // We need to clear the stat cache, as we have just written to the file
        StatCache::clear($path);
        $size = yield Amp\File\size($path);

        print sprintf("%s has a size of %.2fMB\n", $path, (float) $size / 1024 / 1024);
    } catch (Amp\Artax\HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The Client::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
