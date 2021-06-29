<?php

use Amp\File\File;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use function Amp\delay;
use function Amp\getCurrentTime;

require __DIR__ . '/../.helper/functions.php';

// https://stackoverflow.com/a/2510540/2373138
function formatBytes(int $size, int $precision = 2): string
{
    $base = \log($size, 1024);
    $suffixes = ['bytes', 'kB', 'MB', 'GB', 'TB'];

    return \round(1024 ** ($base - \floor($base)), $precision) . ' ' . $suffixes[(int) $base];
}

Loop::run(static function (): \Generator {
    try {
        $start = getCurrentTime();

        // Instantiate the HTTP client
        $client = HttpClientBuilder::buildDefault();

        $request = new Request('https://1906714720.rsc.cdn77.org/img/cdn77-test-14mb.jpg');
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
            "HTTP/%s %d %s\r\n%s\r\n\r\n",
            $response->getProtocolVersion(),
            $response->getStatus(),
            $response->getReason(),
            (string) $response->getRequest()->getUri()
        );

        foreach ($response->getHeaders() as $field => $values) {
            foreach ($values as $value) {
                print "$field: $value\r\n";
            }
        }

        print "\n";

        $path = \tempnam(\sys_get_temp_dir(), "artax-streaming-");

        /** @var File $file */
        $file = yield Amp\File\openFile($path, "w");

        $bytes = 0;

        // The response body is an instance of Payload, which allows buffering or streaming by the consumers choice.
        // We could also use Amp\ByteStream\pipe() here, but we want to show some progress.
        while (null !== $chunk = yield $response->getBody()->read()) {
            yield $file->write($chunk);
            $bytes += \strlen($chunk);

            yield delay(100);

            \printf("\33[2K\r%s with peak memory usage of %.2fMB.", formatBytes($bytes), (float) \memory_get_peak_usage(true) / 1024 / 1024);
        }

        yield $file->close();

        print \sprintf(
            "\rDone in %.2f seconds with peak memory usage of %.2fMB.\n",
            (getCurrentTime() - $start) / 1000,
            (float) \memory_get_peak_usage(true) / 1024 / 1024
        );

        $size = yield Amp\File\getSize($path);

        print \sprintf("%s has a size of %.2fMB\r\n", $path, (float) $size / 1024 / 1024);
    } catch (HttpException $error) {
        // If something goes wrong Amp will throw the exception where the promise was yielded.
        // The HttpClient::request() method itself will never throw directly, but returns a promise.
        echo $error;
    }
});
