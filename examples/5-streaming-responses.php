<?php

use Amp\File\Handle;
use Amp\File\StatCache;
use Amp\Http\Client\Client;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;

require __DIR__ . '/../vendor/autoload.php';

// https://stackoverflow.com/a/2510540/2373138
function formatBytes(int $size, int $precision = 2)
{
    $base = \log($size, 1024);
    $suffixes = ['bytes', 'kB', 'MB', 'GB', 'TB'];

    return \round(1024 ** ($base - \floor($base)), $precision) . ' ' . $suffixes[(int) $base];
}

Loop::run(static function () {
    try {
        $start = \microtime(1);

        // Instantiate the HTTP client
        $client = new Client;

        $request = new Request('http://speed.hetzner.de/100MB.bin');
        $request->setBodySizeLimit(128 * 1024 * 1024); // 128 MB
        $request->setTransferTimeout(120 * 1000); // 120 seconds

        // Make an asynchronous HTTP request
        $promise = $client->request($request);

        // Client::request() is asynchronous! It doesn't return a response. Instead, it returns a promise to resolve the
        // response at some point in the future when we've received the headers of the response. Here we use yield which
        // pauses the execution of the current coroutine until the promise resolves. Amp will automatically continue the
        // coroutine then.
        /** @var Response $response */
        $response = yield $promise;

        // Output the results
        \printf(
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

        $path = \tempnam(\sys_get_temp_dir(), "artax-streaming-");

        /** @var Handle $file */
        $file = yield Amp\File\open($path, "w");

        $bytes = 0;

        // The response body is an instance of Payload, which allows buffering or streaming by the consumers choice.
        // We could also use Amp\ByteStream\pipe() here, but we want to show some progress.
        while (null !== $chunk = yield $response->getBody()->read()) {
            yield $file->write($chunk);
            $bytes += \strlen($chunk);

            print "\r" . formatBytes($bytes) . '    '; // blanks to remove previous output
        }

        yield $file->close();

        print \sprintf(
            "\rDone in %.2f seconds with peak memory usage of %.2fMB.\n",
            \microtime(1) - $start,
            (float) \memory_get_peak_usage(true) / 1024 / 1024
        );

        // We need to clear the stat cache, as we have just written to the file
        StatCache::clear($path);
        $size = yield Amp\File\size($path);

        print \sprintf("%s has a size of %.2fMB\n", $path, (float) $size / 1024 / 1024);
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The Client::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
