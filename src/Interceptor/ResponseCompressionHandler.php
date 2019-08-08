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

final class ResponseCompressionHandler implements NetworkInterceptor
{
    private $hasZlib;

    public function __construct()
    {
        $this->hasZlib = \extension_loaded('zlib');
    }

    public function interceptNetworkRequest(
        Request $request,
        CancellationToken $cancellationToken,
        Stream $stream
    ): Promise {
        return call(function () use ($request, $cancellationToken, $stream) {
            $decodeResponse = false;

            // If a header is manually set, we won't interfere
            if (!$request->hasHeader('accept-encoding')) {
                $request = $this->addAcceptEncodingHeader($request);
                $decodeResponse = true;
            }

            /** @var Response $response */
            $response = yield $stream->request($request, $cancellationToken);

            if ($decodeResponse && ($encoding = $this->determineCompressionEncoding($response))) {
                /** @noinspection PhpUnhandledExceptionInspection */
                $response = $response->withBody(new ZlibInputStream($response->getBody(), $encoding));
            }

            return $response;
        });
    }

    private function addAcceptEncodingHeader(Request $request): Request
    {
        if ($this->hasZlib) {
            return $request->withHeader('Accept-Encoding', 'gzip, deflate, identity');
        }

        return $request;
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
