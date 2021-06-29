<?php

use Amp\File\File;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Loop;
use function Amp\getCurrentTime;

require __DIR__ . '/../.helper/functions.php';

// https://stackoverflow.com/a/2510540/2373138
function formatBytes(int $size, int $precision = 2): string
{
    $base = \log($size, 1024);
    $suffixes = ['bytes', 'kB', 'MB', 'GB', 'TB'];

    return \round(1024 ** ($base - \floor($base)), $precision) . ' ' . $suffixes[(int) $base];
}

function fetch(string $uri, array $protocolVersions): \Generator
{
    try {
        $start = getCurrentTime();

        // Instantiate the HTTP client
        $client = HttpClientBuilder::buildDefault();

        $request = new Request($uri);
        $request->setProtocolVersions($protocolVersions);
        $request->setBodySizeLimit(16 * 1024 * 1024); // 128 MB
        $request->setTransferTimeout(120 * 1000); // 120 seconds

        /** @var Response $response */
        $response = yield $client->request($request);

        print "\n";

        $path = \tempnam(\sys_get_temp_dir(), "artax-streaming-");

        /** @var File $file */
        $file = yield Amp\File\openFile($path, "w");

        $bytes = 0;

        while (null !== $chunk = yield $response->getBody()->read()) {
            yield $file->write($chunk);
            $bytes += \strlen($chunk);

            print "\r" . formatBytes($bytes) . '    '; // blanks to remove previous output
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
}

Loop::run(static function () {
    yield from fetch('http://1153288396.rsc.cdn77.org//img/cdn77-test-14mb.jpg', ['1.1']);
    yield from fetch('https://1906714720.rsc.cdn77.org/img/cdn77-test-14mb.jpg', ['2']);
});
