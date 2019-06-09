<?php

namespace Amp\Http\Client\Internal;

use Amp\ByteStream\OutputStream;
use Amp\ByteStream\StreamException;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\SocketException;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Rfc7230;
use Amp\Promise;
use function Amp\call;

final class RequestWriter
{
    public static function writeRequest(OutputStream $socket, Request $request, string $protocolVersion): Promise
    {
        return call(static function () use ($socket, $request, $protocolVersion) {
            try {
                $rawHeaders = self::generateRawHeader($request, $protocolVersion);
                yield $socket->write($rawHeaders);

                $body = $request->getBody()->createBodyStream();
                $chunking = $request->getHeader("transfer-encoding") === "chunked";
                $remainingBytes = $request->getHeader("content-length");

                if ($chunking && $protocolVersion === "1.0") {
                    throw new HttpException("Can't send chunked bodies over HTTP/1.0");
                }

                // We always buffer the last chunk to make sure we don't write $contentLength bytes if the body is too long.
                $buffer = "";

                while (null !== $chunk = yield $body->read()) {
                    if ($chunk === "") {
                        continue;
                    }

                    if ($chunking) {
                        $chunk = \dechex(\strlen($chunk)) . "\r\n" . $chunk . "\r\n";
                    } elseif ($remainingBytes !== null) {
                        $remainingBytes -= \strlen($chunk);

                        if ($remainingBytes < 0) {
                            throw new HttpException("Body contained more bytes than specified in Content-Length, aborting request");
                        }
                    }

                    yield $socket->write($buffer);
                    $buffer = $chunk;
                }

                // Flush last buffered chunk.
                yield $socket->write($buffer);

                if ($chunking) {
                    yield $socket->write("0\r\n\r\n");
                } elseif ($remainingBytes !== null && $remainingBytes > 0) {
                    throw new HttpException("Body contained fewer bytes than specified in Content-Length, aborting request");
                }
            } catch (StreamException $exception) {
                throw new SocketException('Socket disconnected prior to response completion');
            }
        });
    }

    /**
     * @param Request $request
     * @param string  $protocolVersion
     *
     * @return string
     *
     * @throws HttpException
     */
    private static function generateRawHeader(Request $request, string $protocolVersion): string
    {
        // TODO: Send absolute URIs in the request line when using a proxy server
        //  Right now this doesn't matter because all proxy requests use a CONNECT
        //  tunnel but this likely will not always be the case.

        $uri = $request->getUri();
        $requestUri = $uri->getPath() ?: '/';

        if ('' !== $query = $uri->getQuery()) {
            $requestUri .= '?' . $query;
        }

        $header = $request->getMethod() . ' ' . $requestUri . ' HTTP/' . $protocolVersion . "\r\n";

        try {
            $header .= Rfc7230::formatHeaders($request->getHeaders());
        } catch (InvalidHeaderException $e) {
            throw new HttpException($e->getMessage());
        }

        return $header . "\r\n";
    }
}
