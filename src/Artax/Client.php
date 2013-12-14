<?php

namespace Artax;

use Alert\Reactor, Alert\ReactorFactory;

class Client implements BlockingClient {

    /** @var \Alert\LibeventReactor|\Alert\NativeReactor */
    private $reactor;
    /** @var AsyncClient */
    private $asyncClient;
    /** @var  Response */
    private $response;
    /** @var  ClientException */
    private $error;
    /** @var  \SplObjectStorage */
    private $pendingMultiRequests;
    /** @var  callable */
    private $onMultiResult;
    /** @var  callable */
    private $onMultiError;

    /**
     * The constructor parameters allow for lazy injection and improved testability. Unless you
     * really know what you're doing you shouldn't specify your own arguments when instantiating
     * Artax\Client objects.
     *
     * @param \Alert\Reactor $reactor
     * @param \Artax\AsyncClient $asyncClient
     */
    function __construct(Reactor $reactor = NULL, AsyncClient $asyncClient = NULL) {
        $this->reactor = $reactor ?: (new ReactorFactory)->select();
        $this->asyncClient = $asyncClient ?: new AsyncClient($this->reactor);
    }

    /**
     * Synchronously request an HTTP resource
     *
     * @param $uriOrRequest string|Request An http:// or https:// URI string or Artax\Request instance
     * @throws \Artax\ClientException On socket-level connection issues
     * @return \Artax\Response A mutable object modeling the raw HTTP response
     */
    function request($uriOrRequest) {
        $onError = function(\Exception $error) { $this->error = $error; };
        $onResponse = function(Response $response) { $this->response = $response; };

        $this->reactor->immediately(function() use ($onResponse, $onError, $uriOrRequest) {
            $this->asyncClient->request($uriOrRequest, $onResponse, $onError);
        });

        while (!($this->response || $this->error)) {
            $this->reactor->tick();
        }

        if ($this->response) {
            $response = $this->response;
            $this->response = NULL;
        } else {
            $error = $this->error;
            $this->error = NULL;
            throw $error;
        }

        return $response;
    }

    /**
     * Synchronously request multiple HTTP resources in parallel
     *
     * Note that while the individual requests in the batch are retrieved in parallel, the function
     * call itself will not return until all requests in the group have completed.
     *
     * @param array $requests An array of URI strings and/or Artax\Request instances
     * @param callable $onEachResult Receives Artax\Response instance on request completion
     * @param callable $onEachError Receives an Exception instance on request completion
     * @return void
     */
    function requestMulti(array $requests, callable $onEachResult, callable $onEachError) {
        $this->pendingMultiRequests = new \SplObjectStorage;
        $this->onMultiResult = $onEachResult;
        $this->onMultiError = $onEachError;

        foreach ($this->normalizeMultiRequests($requests) as $requestKey => $request) {
            $onResponse = function(Response $response) use ($request) {
                $this->onMultiResult($request, $response);
            };
            $onError = function(\Exception $error) use ($request) {
                $this->onMultiError($request, $error);
            };

            $this->reactor->immediately(function() use ($onResponse, $onError, $request) {
                $this->asyncClient->request($request, $onResponse, $onError);
            });

            $this->pendingMultiRequests->attach($request, $requestKey);
        }

        $this->reactor->run();
    }

    private function normalizeMultiRequests(array $requests) {
        if (empty($requests)) {
            throw new \InvalidArgumentException(
                'Request array must not be empty'
            );
        }

        $normalized = [];

        foreach ($requests as $requestKey => $request) {
            $normalized[$requestKey] = ($request instanceof Request)
                ? $request
                : (new Request)->setUri($request);
        }

        return $normalized;
    }

    private function onMultiResult(Request $request, Response $response) {
        $requestKey = $this->clearPendingMultiRequest($request);
        $onEachResponse = $this->onMultiResult;
        $onEachResponse($requestKey, $response);
    }

    private function clearPendingMultiRequest(Request $request) {
        $requestKey = $this->pendingMultiRequests->offsetGet($request);
        $this->pendingMultiRequests->detach($request);
        if (!$this->pendingMultiRequests->count()) {
            $this->reactor->stop();
        }

        return $requestKey;
    }

    private function onMultiError(Request $request, \Exception $error) {
        $requestKey = $this->clearPendingMultiRequest($request);
        $onEachError = $this->onMultiError;
        $onEachError($requestKey, $error);
    }

    /**
     * Assign multiple client options from a key-value array
     *
     * @param array $options An array matching option name keys to option values
     * @throws \DomainException On unknown option key
     * @return void
     */
    function setAllOptions(array $options) {
        $this->asyncClient->setAllOptions($options);
    }

    /**
     * Assign a client option
     *
     * @param       $option
     * @param mixed $value Option value
     * @throws \DomainException On unknown option key
     * @return void
     */
    function setOption($option, $value) {
        $this->asyncClient->setOption($option, $value);
    }

    /**
     * Attach an array of event observations
     *
     * @param array $eventListenerMap
     *
     * @internal param array $listeners A key-value array mapping event names to callable listeners
     * @return \Artax\Observation
     */
    function addObservation(array $eventListenerMap) {
        return $this->asyncClient->addObservation($eventListenerMap);
    }

    /**
     * Cancel the specified Observation
     *
     * @param Observation $observation
     * @return void
     */
    function removeObservation(Observation $observation) {
        $this->asyncClient->removeObservation($observation);
    }

    /**
     * Clear all existing observations of this Observable
     *
     * @return void
     */
    function removeAllObservations() {
        $this->asyncClient->removeAllObservations();
    }

    /**
     * Cancel a specific outstanding request
     *
     * @param \Artax\Request $request
     * @return void
     */
    function cancel(Request $request) {
        $this->asyncClient->cancel($request);
        $this->clearPendingMultiRequest($request);
    }

    /**
     * Cancel all outstanding requests
     *
     * @return void
     */
    function cancelAll() {
        $this->asyncClient->cancelAll();
        $this->pendingMultiRequests = new \SplObjectStorage;
        $this->reactor->stop();
    }

}
