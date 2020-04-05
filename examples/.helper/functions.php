<?php

use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Rfc7230;

require __DIR__ . '/../../vendor/autoload.php';

function dumpRequestTrace(Request $request): void
{
    \printf(
        "%s %s HTTP/%s\r\n",
        $request->getMethod(),
        (string) $request->getUri(),
        \implode('+', $request->getProtocolVersions())
    );

    /** @noinspection PhpUnhandledExceptionInspection */
    print Rfc7230::formatHeaders($request->getHeaders()) . "\r\n\r\n";
}

function dumpResponseTrace(Response $response): void
{
    \printf(
        "HTTP/%s %d %s\r\n",
        $response->getProtocolVersion(),
        $response->getStatus(),
        $response->getReason()
    );

    /** @noinspection PhpUnhandledExceptionInspection */
    print Rfc7230::formatHeaders($response->getHeaders()) . "\r\n\r\n";
}

function dumpResponseBodyPreview(string $body): void
{
    $bodyLength = \strlen($body);

    if ($bodyLength < 250) {
        print $body . "\r\n";
    } else {
        print \substr($body, 0, 250) . "\r\n\r\n";
        print($bodyLength - 250) . " more bytes\r\n";
    }
}
