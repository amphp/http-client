<?php

namespace Amp\Http\Client\Connection;

use Amp\ByteStream\IteratorStream;
use Amp\ByteStream\StreamException;
use Amp\CancellationToken;
use Amp\CancellationTokenSource;
use Amp\CancelledException;
use Amp\Deferred;
use Amp\Emitter;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\CombinedCancellationToken;
use Amp\Http\Client\Internal\RequestWriter;
use Amp\Http\Client\Internal\ResponseBodyStream;
use Amp\Http\Client\ParseException;
use Amp\Http\Client\Request;
use Amp\Http\Client\RequestBody;
use Amp\Http\Client\Response;
use Amp\Http\Client\SocketException;
use Amp\Http\Client\TimeoutException;
use Amp\Loop;
use Amp\NullCancellationToken;
use Amp\Promise;
use Amp\Socket\EncryptableSocket;
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\TlsInfo;
use Amp\Success;
use Amp\TimeoutCancellationToken;
use function Amp\asyncCall;
use function Amp\call;

/**
 * Socket client implementation.
 *
 * @see Client
 */
final class Http1Connection implements Connection
{
    /** @var Socket */
    private $socket;

    /** @var bool */
    private $busy = false;

    /** @var int Number of requests made on this connection. */
    private $requestCounter = 0;

    /** @var string|null Keep alive timeout watcher ID. */
    private $timeoutWatcher;

    /** @var int Keep-Alive timeout from last response. */
    private $priorTimeout = self::MAX_KEEP_ALIVE_TIMEOUT;

    /** @var callable[]|null */
    private $onClose = [];

    public function __construct(Socket $socket)
    {
        $this->socket = $socket;

        if ($this->socket->isClosed()) {
            $this->onClose = null;
        }
    }

    public function isBusy(): bool
    {
        return $this->busy;
    }

    public function onClose(callable $onClose): void
    {
        if ($this->socket->isClosed()) {
            Promise\rethrow(call($onClose, $this));
            return;
        }

        $this->onClose[] = $onClose;
    }

    public function close(): Promise
    {
        $this->socket->close();

        if ($this->onClose !== null) {
            $onClose = $this->onClose;
            $this->onClose = null;

            foreach ($onClose as $callback) {
                Promise\rethrow(call($callback, $this));
            }
        }

        return new Success;
    }

    public function getLocalAddress(): SocketAddress
    {
        return $this->socket->getLocalAddress();
    }

    public function getRemoteAddress(): SocketAddress
    {
        return $this->socket->getRemoteAddress();
    }

    public function getTlsInfo(): ?TlsInfo
    {
        return $this->socket instanceof EncryptableSocket ? $this->socket->getTlsInfo() : null;
    }

    /** @inheritdoc */
    public function request(Request $request, ?CancellationToken $cancellation = null): Promise
    {
        return call(function () use ($request, $cancellation) {
            $cancellation = $cancellation ?? new NullCancellationToken;

            $this->busy = true;
            ++$this->requestCounter;

            if ($this->timeoutWatcher !== null) {
                Loop::cancel($this->timeoutWatcher);
            }

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

            $cancellationId = $readingCancellation->subscribe([$this, 'close']);

            $busy = &$this->busy;
            $completionDeferred->promise()->onResolve(static function () use (&$busy, $readingCancellation, $cancellationId) {
                $readingCancellation->unsubscribe($cancellationId);
                $busy = false;
            });

            try {
                yield RequestWriter::writeRequest($this->socket, $request, $protocolVersion);

                return yield from $this->doRead($request, $cancellation, $readingCancellation, $completionDeferred);
            } catch (HttpException $e) {
                $cancellation->throwIfRequested();

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

        // Always normalize this as last item, because we need to strip sensitive headers
        $request = $this->normalizeTraceRequest($request);

        return $request;
    }

    /**
     * @param Request           $request
     * @param CancellationToken $originalCancellation
     * @param CancellationToken $readingCancellation
     * @param Deferred          $completionDeferred
     *
     * @return \Generator
     * @throws ParseException
     * @throws SocketException
     * @throws CancelledException
     */
    private function doRead(
        Request $request,
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

        $parser = new Http1Parser($request, $bodyCallback);

        try {
            while (null !== $chunk = yield $this->socket->read()) {
                $response = $parser->parse($chunk);
                if ($response === null) {
                    continue;
                }

                $bodyCancellationSource = new CancellationTokenSource;
                $bodyCancellationToken = new CombinedCancellationToken($readingCancellation, $bodyCancellationSource->getToken());
                $bodyCancellationToken->subscribe([$this, 'close']);

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
                            } while (null !== $chunk = yield $this->socket->read());

                            $originalCancellation->throwIfRequested();

                            if ($readingCancellation->isRequested()) {
                                throw new TimeoutException('Allowed transfer timeout exceeded, took longer than ' . $request->getTransferTimeout() . ' ms');
                            }

                            $bodyCancellationToken->throwIfRequested();

                            // Ignore check if neither content-length nor chunked encoding are given.
                            if (!$parser->isComplete() && $parser->getState() !== Http1Parser::BODY_IDENTITY_EOF) {
                                throw new SocketException('Socket disconnected prior to response completion');
                            }
                        }

                        if ($parser->getState() !== Http1Parser::BODY_IDENTITY_EOF && $timeout = $this->determineKeepAliveTimeout($response)) {
                            $this->timeoutWatcher = Loop::delay($timeout * 1000, [$this, 'close']);
                            Loop::unreference($this->timeoutWatcher);
                        } else {
                            $this->close();
                        }

                        $bodyEmitter->complete();
                        $completionDeferred->resolve();
                    } catch (\Throwable $e) {
                        $this->close();

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

    private function determineKeepAliveTimeout(Response $response): int
    {
        $request = $response->getRequest();

        $requestConnHeader = $request->getHeader('connection');
        $responseConnHeader = $response->getHeader('connection');

        if (!\strcasecmp($requestConnHeader, 'close')) {
            return 0;
        }

        if ($response->getProtocolVersion() === '1.0') {
            return 0;
        }

        if (\strcasecmp($responseConnHeader, 'keep-alive')) {
            return 0;
        }

        $responseKeepAliveHeader = $response->getHeader('keep-alive');

        if ($responseKeepAliveHeader === null) {
            return $this->priorTimeout;
        }

        $parts = \array_map('trim', \explode(',', $responseKeepAliveHeader));

        $params = [];
        foreach ($parts as $part) {
            [$key, $value] = \array_map('trim', \explode('=', $part)) + [null, null];
            $params[$key] = (int) $value;
        }

        return $this->priorTimeout = \min(\max(0, $params['timeout'] ?? 0), self::MAX_KEEP_ALIVE_TIMEOUT);
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
