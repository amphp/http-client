<?php

namespace Amp\Http\Client\Interceptor;

use Amp\ByteStream\ZlibInputStream;
use Amp\CancellationToken;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Promise;
use function Amp\call;

final class DecompressResponse implements NetworkInterceptor
{
    private $hasZlib;

    public function __construct()
    {
        $this->hasZlib = \extension_loaded('zlib');
    }

    public function interceptNetwork(
        Request $request,
        CancellationToken $cancellation,
        Stream $stream
    ): Promise {
        return call(function () use ($request, $cancellation, $stream) {
            $decodeResponse = false;

            // If a header is manually set, we won't interfere
            if (!$request->hasHeader('accept-encoding')) {
                $this->addAcceptEncodingHeader($request);
                $decodeResponse = true;
            }

            /** @var Response $response */
            $response = yield $stream->request($request, $cancellation);

            if ($decodeResponse && ($encoding = $this->determineCompressionEncoding($response))) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $response->setBody(new ZlibInputStream($response->getBody(), $encoding));
            }

            return $response;
        });
    }

    private function addAcceptEncodingHeader(Request $request): void
    {
        if ($this->hasZlib) {
            $request->setHeader('Accept-Encoding', 'gzip, deflate, identity');
        }
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
