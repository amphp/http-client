<?php

namespace Amp\Http\Client;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Http\Client\Internal\CallableNetworkClient;
use Amp\Http\Client\Internal\CombinedCancellationToken;
use Amp\Http\Client\Internal\NetworkInterceptorClient;
use Amp\Http\Client\Internal\Parser;
use Amp\Http\Client\Internal\RequestWriter;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\ResourceSocket;
use Amp\Socket\SocketPool;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use function Amp\asyncCall;
use function Amp\call;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class SocketClient implements Client
{
    public const DEFAULT_USER_AGENT = 'Mozilla/5.0 (compatible; amphp/http-client)';

    private $socketPool;
    private $networkInterceptors;

    public function __construct(SocketPool $socketPool)
    {
        $this->socketPool = $socketPool;
        $this->networkInterceptors = [];
    }

    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): void
    {
        $this->networkInterceptors[] = $networkInterceptor;
    }

    public function request(Request $request, CancellationToken $cancellation = null): Promise
    {
        return call(function () use ($request, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;

            $isHttps = $request->getUri()->getScheme() === 'https';
            $defaultPort = $isHttps ? 443 : 80;

            $authority = $request->getUri()->getHost() . ':' . ($request->getUri()->getPort() ?: $defaultPort);
            $socketUri = "tcp://{$authority}";

            $connectContext = new ConnectContext;

            if ($request->getUri()->getScheme() === 'https') {
                $tlsContext = ($connectContext->getTlsContext() ?? new ClientTlsContext($request->getUri()->getHost()))
                    ->withPeerName($request->getUri()->getHost())
                    ->withPeerCapturing();

                $connectContext = $connectContext->withTlsContext($tlsContext);
            }

            try {
                $checkoutCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTcpConnectTimeout()));

                /** @var EncryptableSocket $socket */
                $socket = yield $this->socketPool->checkout($socketUri, $connectContext, $checkoutCancellationToken);
            } catch (Socket\SocketException $e) {
                throw new SocketException(\sprintf("Connection to '%s' failed", $authority), 0, $e);
            } catch (CancelledException $e) {
                // In case of a user cancellation request, throw the expected exception
                $cancellation->throwIfRequested();

                // Otherwise we ran into a timeout of our TimeoutCancellationToken
                throw new TimeoutException(\sprintf("Connection to '%s' timed out, took longer than " . $request->getTcpConnectTimeout() . ' ms', $authority)); // don't pass $e
            }

            try {
                try {
                    $socket->reference();

                    if ($isHttps) {
                        $tlsState = $socket->getTlsState();
                        if ($tlsState === EncryptableSocket::TLS_STATE_DISABLED) {
                            $tlsCancellationToken = new CombinedCancellationToken($cancellation, new TimeoutCancellationToken($request->getTlsHandshakeTimeout()));
                            yield $socket->setupTls($tlsCancellationToken);
                        } elseif ($tlsState !== EncryptableSocket::TLS_STATE_ENABLED) {
                            throw new SocketException('Failed to setup TLS connection, connection was in an unexpected TLS state (' . $tlsState . ')');
                        }
                    }
                } catch (StreamException $exception) {
                    throw new SocketException(\sprintf("Connection to '%s' closed during TLS handshake", $authority), 0, $exception);
                } catch (CancelledException $e) {
                    // In case of a user cancellation request, throw the expected exception
                    $cancellation->throwIfRequested();

                    // Otherwise we ran into a timeout of our TimeoutCancellationToken
                    throw new TimeoutException(\sprintf("TLS handshake with '%s' @ '%s' timed out, took longer than " . $request->getTlsHandshakeTimeout() . ' ms', $authority, $socket->getRemoteAddress()->toString())); // don't pass $e
                }

                $connectionInfo = new ConnectionInfo($socket->getLocalAddress(), $socket->getRemoteAddress(), $socket->getTlsInfo());
                $client = new CallableNetworkClient(function () use (
                    $request,
                    $socket,
                    $connectionInfo,
                    $cancellation
                ): Promise {
                    return $this->send($request, $socket, $connectionInfo, $cancellation);
                });

                $client = new NetworkInterceptorClient($client, $connectionInfo, ...$this->networkInterceptors);

                /** @var Response $response */
                $response = yield $client->request($request, $cancellation);

                $response->getCompletionPromise()->onResolve(function ($error) use ($socket) {
                    if ($error || $socket->isClosed()) {
                        $this->socketPool->clear($socket);
                        $socket->close();
                    } else {
                        $socket->unreference();
                        $this->socketPool->checkin($socket);
                    }
                });

                return $response;
            } catch (\Throwable $e) {
                $this->socketPool->clear($socket);
                $socket->close();

                throw $e;
            }
        });
    }

    private function buildRequest(Request $request): \Generator
    {
        /** @var array $headers */
        $headers = yield $request->getBody()->getHeaders();
        foreach ($headers as $name => $header) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeaders([$name => $header]);
            }
        }

        /** @var Request $request */
        $request = yield from $this->normalizeRequestBodyHeaders($request);
        $request = $this->normalizeRequestHeaders($request);

        // Always normalize this as last item, because we need to strip sensitive headers
        $request = $this->normalizeTraceRequest($request);

        return $request;
    }

    private function send(
        Request $request,
        EncryptableSocket $socket,
        ConnectionInfo $connectionInfo,
        CancellationToken $cancellation = null
    ): Promise {
        return call(function () use ($request, $socket, $connectionInfo, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;

            /** @var Request $request */
            $request = yield from $this->buildRequest($request);
            $protocolVersion = $this->determineProtocolVersion($request);

            $completionDeferred = new Deferred;

            if ($request->getTransferTimeout() > 0) {
                $timeoutToken = new TimeoutCancellationToken($request->getTransferTimeout());
                $readingCancellation = new CombinedCancellationToken($cancellation, $timeoutToken);
            } else {
                $readingCancellation = $cancellation;
            }

            $cancellationId = $readingCancellation->subscribe(static function () use ($socket) {
                $socket->close();
            });

            $completionDeferred->promise()->onResolve(static function () use ($readingCancellation, $cancellationId) {
                $readingCancellation->unsubscribe($cancellationId);
            });

            try {
                yield RequestWriter::writeRequest($socket, $request, $protocolVersion);

                return yield from $this->doRead($socket, $request, $connectionInfo, $cancellation, $readingCancellation, $completionDeferred);
            } catch (HttpException $e) {
                $cancellation->throwIfRequested();

                throw $e;
            }
        });
    }

    /**
     * @param EncryptableSocket $socket
     * @param Request           $request
     * @param ConnectionInfo    $connectionInfo
     * @param CancellationToken $originalCancellation
     * @param CancellationToken $readingCancellation
     * @param Deferred          $completionDeferred
     *
     * @return \Generator
     * @throws ParseException
     * @throws SocketException
     */
    private function doRead(
        EncryptableSocket $socket,
        Request $request,
        ConnectionInfo $connectionInfo,
        CancellationToken $originalCancellation,
        CancellationToken $readingCancellation,
        Deferred $completionDeferred
    ): \Generator {
        $bodyEmitter = new Emitter;

        $backpressure = new Success;
        $bodyCallback = $request->isDiscardBody()
            ? null
            : static function ($data) use ($bodyEmitter, &$backpressure) {
                $backpressure = $bodyEmitter->emit($data);
            };

        $parser = new Parser($request, $connectionInfo, $bodyCallback);

        try {
            while (null !== $chunk = yield $socket->read()) {
                $response = $parser->parse($chunk);
                if ($response === null) {
                    continue;
                }

                $bodyCancellationSource = new CancellationTokenSource;
                $bodyCancellationToken = new CombinedCancellationToken($readingCancellation, $bodyCancellationSource->getToken());
                $bodyCancellationToken->subscribe(static function () use ($socket) {
                    $socket->close();
                });

                $response = $response
                    ->withBody(new ResponseBodyStream(new IteratorStream($bodyEmitter->iterate()), $bodyCancellationSource))
                    ->withCompletionPromise($completionDeferred->promise());

                // Read body async
                asyncCall(function () use (
                    $parser,
                    $request,
                    $response,
                    $bodyEmitter,
                    $completionDeferred,
                    $originalCancellation,
                    $readingCancellation,
                    $bodyCancellationToken,
                    $socket,
                    &$backpressure
                ) {
                    try {
                        // Required, otherwise responses without body hang
                        if (!$parser->isComplete()) {
                            // Directly parse again in case we already have the full body but aborted parsing
                            // to resolve promise with headers.
                            $chunk = null;

                            do {
                                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                                $parser->parse($chunk);
                                /** @noinspection NotOptimalIfConditionsInspection */
                                if ($parser->isComplete()) {
                                    break;
                                }

                                if (!$backpressure instanceof Success) {
                                    yield $this->withCancellation($backpressure, $bodyCancellationToken);
                                }
                            } while (null !== $chunk = yield $socket->read());

                            $originalCancellation->throwIfRequested();

                            if ($readingCancellation->isRequested()) {
                                throw new TimeoutException('Allowed transfer timeout exceeded, took longer than ' . $request->getTransferTimeout() . ' ms');
                            }

                            $bodyCancellationToken->throwIfRequested();

                            // Ignore check if neither content-length nor chunked encoding are given.
                            if (!$parser->isComplete() && $parser->getState() !== Parser::BODY_IDENTITY_EOF) {
                                throw new SocketException('Socket disconnected prior to response completion');
                            }
                        }

                        if ($this->shouldCloseSocketAfterResponse($response) || $parser->getState() === Parser::BODY_IDENTITY_EOF) {
                            $socket->close();
                        }

                        $bodyEmitter->complete();
                        $completionDeferred->resolve();
                    } catch (\Throwable $e) {
                        $socket->close();

                        $bodyEmitter->fail($e);
                        $completionDeferred->fail($e);
                    }
                });

                return $response;
            }

            $originalCancellation->throwIfRequested();

            throw new SocketException('Receiving the response headers failed, because the socket closed early');
        } catch (StreamException $e) {
            throw new SocketException('Receiving the response headers failed: ' . $e->getMessage());
        }
    }

    private function withCancellation(Promise $promise, CancellationToken $cancellationToken): Promise
    {
        $deferred = new Deferred;
        $newPromise = $deferred->promise();

        $promise->onResolve(static function ($error, $value) use (&$deferred) {
            if ($deferred) {
                if ($error) {
                    $deferred->fail($error);
                    $deferred = null;
                } else {
                    $deferred->resolve($value);
                    $deferred = null;
                }
            }
        });

        $cancellationSubscription = $cancellationToken->subscribe(static function ($e) use (&$deferred) {
            if ($deferred) {
                $deferred->fail($e);
                $deferred = null;
            }
        });

        $newPromise->onResolve(static function () use ($cancellationToken, $cancellationSubscription) {
            $cancellationToken->unsubscribe($cancellationSubscription);
        });

        return $newPromise;
    }

    private function normalizeRequestBodyHeaders(Request $request): \Generator
    {
        if ($request->hasHeader("transfer-encoding")) {
            return $request->withoutHeader("content-length");
        }

        if ($request->hasHeader("content-length")) {
            return $request;
        }

        /** @var RequestBody $body */
        $body = $request->getBody();
        $bodyLength = yield $body->getBodyLength();

        if ($bodyLength === 0) {
            $request = $request->withHeader('content-length', '0');
            $request = $request->withoutHeader('transfer-encoding');
        } elseif ($bodyLength > 0) {
            $request = $request->withHeader("content-length", $bodyLength);
            $request = $request->withoutHeader("transfer-encoding");
        } else {
            $request = $request->withHeader("transfer-encoding", "chunked");
        }

        return $request;
    }

    private function normalizeRequestHeaders(Request $request): Request
    {
        $request = $this->normalizeRequestHostHeader($request);
        $request = $this->normalizeRequestUserAgent($request);
        $request = $this->normalizeRequestAcceptHeader($request);

        return $request;
    }

    private function normalizeTraceRequest(Request $request): Request
    {
        $method = $request->getMethod();

        if ($method !== 'TRACE') {
            return $request;
        }

        // https://tools.ietf.org/html/rfc7231#section-4.3.8
        /** @var Request $request */
        $request = $request->withBody(null);

        // Remove all body and sensitive headers
        $request = $request->withHeaders([
            "transfer-encoding" => [],
            "content-length" => [],
            "authorization" => [],
            "proxy-authorization" => [],
            "cookie" => [],
        ]);

        return $request;
    }

    private function normalizeRequestHostHeader(Request $request): Request
    {
        if ($request->hasHeader('host')) {
            $host = $request->getHeader('host');
        } else {
            $host = $request->getUri()->withUserInfo('')->getAuthority();
        }

        // Though servers are supposed to be able to handle standard port names on the end of the
        // Host header some fail to do this correctly. As a result, we strip the port from the end
        // if it's a standard 80 or 443
        if ($request->getUri()->getScheme() === 'http' && \strpos($host, ':80') === \strlen($host) - 3) {
            $request = $request->withHeader('host', \substr($host, 0, -3));
        } elseif ($request->getUri()->getScheme() === 'https' && \strpos($host, ':443') === \strlen($host) - 4) {
            $request = $request->withHeader('host', \substr($host, 0, -4));
        } else {
            $request = $request->withHeader('host', $host);
        }

        return $request;
    }

    private function normalizeRequestUserAgent(Request $request): Request
    {
        if ($request->hasHeader('user-agent')) {
            return $request;
        }

        return $request->withHeader('user-agent', self::DEFAULT_USER_AGENT);
    }

    private function normalizeRequestAcceptHeader(Request $request): Request
    {
        if ($request->hasHeader('accept')) {
            return $request;
        }

        return $request->withHeader('accept', '*/*');
    }

    private function shouldCloseSocketAfterResponse(Response $response): bool
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('connection');
        $responseConnHeader = $response->getHeader('connection');

        if ($requestConnHeader && !\strcasecmp($requestConnHeader, 'close')) {
            return true;
        }

        if ($responseConnHeader && !\strcasecmp($responseConnHeader, 'close')) {
            return true;
        }

        if (!$responseConnHeader && $response->getProtocolVersion() === '1.0') {
            return true;
        }

        return false;
    }

    private function determineProtocolVersion(Request $request): string
    {
        $protocolVersions = $request->getProtocolVersions();

        if (\in_array("1.1", $protocolVersions, true)) {
            return "1.1";
        }

        if (\in_array("1.0", $protocolVersions, true)) {
            return "1.0";
        }

        throw new HttpException("None of the requested protocol versions is supported: " . \implode(", ", $protocolVersions));
    }
}
