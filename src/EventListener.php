<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Connection\ConnectionPool;
use Amp\Http\Client\Connection\Stream;
use Amp\Promise;

/**
 * Allows listening to more fine granular events than interceptors are able to achieve.
 *
 * All event listener methods might be called multiple times for a single request. The implementing listener is
 * responsible to detect another call, e.g. via attributes in the request.
 */
interface EventListener
{
    /**
     * Called at the very beginning of {@see DelegateHttpClient::request()}.
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function startRequest(Request $request): Promise;

    /**
     * Optionally called by {@see ConnectionPool::getStream()} before DNS resolution is started.
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function startDnsResolution(Request $request): Promise;

    /**
     * Optionally called by {@see ConnectionPool::getStream()} after DNS resolution is completed.
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function completeDnsResolution(Request $request): Promise;

    /**
     * Called by {@see ConnectionPool::getStream()} before a new connection is initiated.
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function startConnectionCreation(Request $request): Promise;

    /**
     * Called by {@see ConnectionPool::getStream()} after a new connection is established and TLS negotiated.
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function completeConnectionCreation(Request $request): Promise;

    /**
     * Called by {@see ConnectionPool::getStream()} before TLS negotiation is started (only if HTTPS is used).
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function startTlsNegotiation(Request $request): Promise;

    /**
     * Called by {@see ConnectionPool::getStream()} after TLS negotiation is successful (only if HTTPS is used).
     *
     * @param Request $request
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function completeTlsNegotiation(Request $request): Promise;

    /**
     * Called by {@see Stream::request()} before the request is sent.
     *
     * @param Request $request
     * @param Stream  $stream
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function startSendingRequest(Request $request, Stream $stream): Promise;

    /**
     * Called by {@see Stream::request()} after the request is sent.
     *
     * @param Request $request
     * @param Stream  $stream
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function completeSendingRequest(Request $request, Stream $stream): Promise;

    /**
     * Called by {@see Stream::request()} after the first response byte is received.
     *
     * @param Request $request
     * @param Stream  $stream
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function startReceivingResponse(Request $request, Stream $stream): Promise;

    /**
     * Called by {@see Stream::request()} after the request is complete.
     *
     * @param Request $request
     * @param Stream  $stream
     *
     * @return Promise Should resolve successfully, otherwise aborts the request.
     */
    public function completeReceivingResponse(Request $request, Stream $stream): Promise;

    /**
     * Called if the request is aborted.
     *
     * @param Request    $request
     * @param \Throwable $cause
     *
     * @return Promise Should resolve successfully.
     */
    public function abort(Request $request, \Throwable $cause): Promise;
}
