<?php

namespace Amp\Http\Client\Connection\Internal;

use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Promise;
use function Amp\call;

/** @internal */
final class RequestNormalizer
{
    public static function normalizeRequest(Request $request): Promise
    {
        return call(static function () use ($request) {
            /** @var array $headers */
            $headers = yield $request->getBody()->getHeaders();
            foreach ($headers as $name => $header) {
                if (!$request->hasHeader($name)) {
                    $request->setHeaders([$name => $header]);
                }
            }

            yield from self::normalizeRequestBodyHeaders($request);

            // Always normalize this as last item, because we need to strip sensitive headers
            self::normalizeTraceRequest($request);

            return $request;
        });
    }

    private static function normalizeRequestBodyHeaders(Request $request): \Generator
    {
        if (!$request->hasHeader('host')) {
            $uri = $request->getUri();
            $scheme = $uri->getScheme();
            $host = $uri->getHost();
            $port = $uri->getPort();

            // Though servers are supposed to be able to handle standard port names on the end of the
            // Host header some fail to do this correctly. As a result, we strip the port from the end
            // if it's a standard 80 or 443
            if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
                $request->setHeader('host', $host);
            } else {
                $request->setHeader('host', $host . ':' . $port);
            }
        }

        if ($request->hasHeader("transfer-encoding")) {
            $request->removeHeader("content-length");

            return;
        }

        if ($request->hasHeader("content-length")) {
            return;
        }

        /** @var RequestBody $body */
        $body = $request->getBody();
        $bodyLength = yield $body->getBodyLength();

        if ($bodyLength === 0) {
            if (!\in_array($request->getMethod(), ['HEAD', 'GET', 'CONNECT'], true) || $request->hasHeader('upgrade')) {
                $request->removeHeader('content-length');
            } else {
                $request->setHeader('content-length', '0');
            }

            $request->removeHeader('transfer-encoding');
        } elseif ($bodyLength > 0) {
            $request->setHeader("content-length", $bodyLength);
            $request->removeHeader("transfer-encoding");
        } else {
            $request->setHeader("transfer-encoding", "chunked");
        }
    }

    private static function normalizeTraceRequest(Request $request): void
    {
        $method = $request->getMethod();

        if ($method !== 'TRACE') {
            return;
        }

        // https://tools.ietf.org/html/rfc7231#section-4.3.8
        $request->setBody(null);

        // Remove all body and sensitive headers
        $request->setHeaders([
            "transfer-encoding" => [],
            "content-length" => [],
            "authorization" => [],
            "proxy-authorization" => [],
            "cookie" => [],
        ]);
    }
}
