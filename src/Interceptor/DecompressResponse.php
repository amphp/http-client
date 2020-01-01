<?php

namespace Amp\Http\Client\Interceptor;

use Amp\ByteStream\ZlibInputStream;
use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Internal\SizeLimitingInputStream;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use function Amp\call;

final class DecompressResponse implements NetworkInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    public const CONTENT_ENCODING_ATTR = 'amp.http.client.decompress.content-encoding';

    private $hasZlib;

    public function __construct()
    {
        $this->hasZlib = \extension_loaded('zlib');
    }

    public function requestViaNetwork(
        Request $request,
        CancellationToken $cancellation,
        Stream $stream
    ): Promise {
        return call(function () use ($request, $cancellation, $stream) {
            // If a header is manually set, we won't interfere
            if ($request->hasHeader('accept-encoding')) {
                return $stream->request($request, $cancellation);
            }

            $this->addAcceptEncodingHeader($request);

            $request->interceptPush(function (Response $response) {
                return $this->decompressResponse($response);
            });

            return $this->decompressResponse(yield $stream->request($request, $cancellation));
        });
    }

    private function addAcceptEncodingHeader(Request $request): void
    {
        if ($this->hasZlib) {
            $request->setHeader('Accept-Encoding', 'gzip, deflate, identity');
        }
    }

    private function decompressResponse(Response $response): Response
    {
        if (($encoding = $this->determineCompressionEncoding($response))) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $sizeLimit = $response->getRequest()->getBodySizeLimit();
            $decompressedBody = new ZlibInputStream($response->getBody(), $encoding);

            $response->getRequest()->setAttribute(self::CONTENT_ENCODING_ATTR, $response->getHeader('content-encoding'));
            $response->setBody(new SizeLimitingInputStream($decompressedBody, $sizeLimit));
            $response->removeHeader('content-encoding');
        }

        return $response;
    }

    private function determineCompressionEncoding(Response $response): int
    {
        if (!$this->hasZlib) {
            return 0;
        }

        if (!$response->hasHeader("content-encoding")) {
            return 0;
        }

        $contentEncodingHeader = \trim($response->getHeader("content-encoding"));

        if (\strcasecmp($contentEncodingHeader, 'gzip') === 0) {
            return \ZLIB_ENCODING_GZIP;
        }

        if (\strcasecmp($contentEncodingHeader, 'deflate') === 0) {
            return \ZLIB_ENCODING_DEFLATE;
        }

        return 0;
    }
}
