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

function dumpResponseBodyPreview(string $body, int $maxLength = 512): void
{
    $bodyLength = \strlen($body);

    if ($bodyLength < $maxLength) {
        print $body . "\r\n";
    } else {
        print \substr($body, 0, $maxLength) . "\r\n\r\n";
        print($bodyLength - $maxLength) . " more bytes\r\n";
    }
}
