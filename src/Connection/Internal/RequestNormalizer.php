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
            // Though servers are supposed to be able to handle standard port names on the end of the
            // Host header some fail to do this correctly. Thankfully PSR-7 recommends to strip the port
            // if it is the standard port for the given scheme.
            $request->setHeader('host', $request->getUri()->withUserInfo('')->getAuthority());
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
            if (\in_array($request->getMethod(), ['HEAD', 'GET', 'CONNECT'], true)) {
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
