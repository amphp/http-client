<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection\Internal;

use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

/** @internal */
final class RequestNormalizer
{
    /**
     * @throws HttpException
     */
    public static function normalizeRequest(Request $request): Request
    {
        if (!$request->hasHeader('host')) {
            // Though servers are supposed to be able to handle standard port names on the end of the
            // Host header some fail to do this correctly. Thankfully PSR-7 recommends to strip the port
            // if it is the standard port for the given scheme.
            $request->setHeader('host', $request->getUri()->withUserInfo('')->getAuthority());
        }

        self::normalizeRequestBodyHeaders($request);

        // Always normalize this as last item, because we need to strip sensitive headers
        self::normalizeTraceRequest($request);

        return $request;
    }

    /**
     * @throws HttpException
     */
    private static function normalizeRequestBodyHeaders(Request $request): void
    {
        $body = $request->getBody();

        $contentType = $body->getContentType();
        if ($contentType !== null) {
            $previousContentType = $request->getHeaderArray('content-type');
            if ($previousContentType !== [] && $previousContentType !== [$contentType]) {
                throw new HttpException('Conflicting content type headers in request and request body: ' . \implode(', ', $previousContentType) . ' / ' . $contentType);
            }

            $request->setHeader('content-type', $contentType);
        }

        if ($request->hasHeader("transfer-encoding")) {
            $request->removeHeader("content-length");

            return;
        }

        $contentLength = $body->getContentLength();
        if ($contentLength === 0 && \in_array($request->getMethod(), ["CONNECT", "GET", "HEAD", "OPTIONS", "CONNECT", "TRACE"], true)) {
            $request->removeHeader('content-length');
            $request->removeHeader('transfer-encoding');
        } elseif ($contentLength !== null) {
            $request->setHeader('content-length', (string) $contentLength);
            $request->removeHeader('transfer-encoding');
        } else {
            $request->removeHeader('content-length');
            $request->setHeader("transfer-encoding", "chunked");
        }
    }

    private static function normalizeTraceRequest(Request $request): void
    {
        if ($request->getMethod() !== 'TRACE') {
            return;
        }

        // https://tools.ietf.org/html/rfc7231#section-4.3.8
        $request->setBody('');

        // Remove all body and sensitive headers
        $request->replaceHeaders([
            "transfer-encoding" => [],
            "content-length" => [],
            "authorization" => [],
            "proxy-authorization" => [],
            "cookie" => [],
        ]);
    }
}
