<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\ByteStream\Compression\DecompressingReadableStream;
use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\Internal\SizeLimitingReadableStream;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;

final class DecompressResponse implements NetworkInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    private bool $hasZlib;

    public function __construct()
    {
        $this->hasZlib = \extension_loaded('zlib');
    }

    public function requestViaNetwork(
        Request $request,
        Cancellation $cancellation,
        Stream $stream
    ): Response {
        // If a header is manually set, we won't interfere
        if ($request->hasHeader('accept-encoding')) {
            return $stream->request($request, $cancellation);
        }

        $this->addAcceptEncodingHeader($request);

        $request->interceptPush(function (Request $request, Response $response): Response {
            return $this->decompressResponse($response);
        });

        return $this->decompressResponse($stream->request($request, $cancellation));
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
            $sizeLimit = $response->getRequest()->getBodySizeLimit();
            $decompressedBody = new DecompressingReadableStream($response->getBody(), $encoding);

            $response->setBody(new SizeLimitingReadableStream($decompressedBody, $sizeLimit));
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

        $contentEncoding = $response->getHeader("content-encoding");

        \assert($contentEncoding !== null);

        $contentEncodingHeader = \trim($contentEncoding);

        if (\strcasecmp($contentEncodingHeader, 'gzip') === 0) {
            return \ZLIB_ENCODING_GZIP;
        }

        if (\strcasecmp($contentEncodingHeader, 'deflate') === 0) {
            return \ZLIB_ENCODING_DEFLATE;
        }

        return 0;
    }
}
