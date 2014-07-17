<?php

namespace Artax;

use Alert\Reactor, Alert\ReactorFactory;

class BlockingClient {
    const MULTI_SUCCESS = 0;
    const MULTI_FAILURE = -1;

    private $reactor;
    private $client;

    /**
     * The BlockingClient is little more than a wrapper around the asynchronous Client class. The
     * constructor parameters here are exposed to allow for easier testing, but they should not
     * be manually injected unless you understand how the async Client works and know explicitly
     * what you're doing. For most users, the following is all that's needed to instantiate
     * a blocking client instance:
     *
     *     $myClient = new BlockingClient;
     *
     * @param Alert\Reactor $reactor
     * @param Artax\Client $client
     */
    public function __construct(Reactor $reactor = null, Client $client = null) {
        $this->reactor = $reactor ?: (new ReactorFactory)->select();
        $this->client = $client ?: new Client($this->reactor);
    }

    /**
     * Make an HTTP request
     *
     * @param string|Artax\Request An HTTP URI or Request instance
     * @param callable $notify optional callback to notify with progress events
     * @throws Artax\ClientException
     * @return Artax\Response
     */
    public function request($uriOrRequest, callable $notify = null) {
        $response = null;
        $promise = $this->client->request($uriOrRequest);

        if ($notify) {
            $promise->onProgress($notify);
        }

        $promise->onResolve(function($error, $result) use (&$response) {
            $this->reactor->stop();
            if ($error) {
                throw $error;
            } else {
                $response = $result;
            }
        });

        $this->reactor->run();

        return $response;
    }

    /**
     * Request multiple HTTP resources in parallel
     *
     * Note: the function itself still blocks. Though all requested resources are retrieved at the
     * same time the function will not return until all requests have completed (or errored out).
     *
     * Results are returned in an array with keys matching those from the original request. Result
     * array values are a two-item indexed array of the following form:
     *
     *     [$succeeded, $responseOrException]
     *
     * If the first parameter is TRUE, the response was successful and the second element is an
     * Artax\Response instance. Otherwise the second element is an Exception object describing the
     * reason for failure.
     *
     * The notification callable functions in the same way as it does with BlockingClient::request()
     * except that it adds a second parameter, $requestKey, to associate the notification with the
     * individual request associated with the event at hand.
     *
     * @param array $uriOrRequestArray
     * @param callable $notify
     * @return array Returns an array of individual request results
     */
    public function requestMulti(array $uriOrRequestArray, callable $notify = null) {
        $results = $promises = [];

        foreach ($uriOrRequestArray as $requestKey => $uriOrRequest) {
            $promise = $this->client->request($uriOrRequest);
            $promise->onProgress(function($data) use (&$results, $requestKey, $notify) {
                list($event, $notification) = $data;

                if ($notify) {
                    $notify($requestKey, $data);
                }

                if ($event === Notify::RESPONSE || $event === Notify::ERROR) {
                    $succeeded = ($event === Notify::RESPONSE);
                    $results[$requestKey] = [$succeeded, $notification];
                }
            });

            $promises[$requestKey] = $promise;
        }

        \After\some($promises)->onResolve(function() {
            $this->reactor->stop();
        });

        $this->reactor->run();

        return $results;
    }

    /**
     * Set an individual Client option
     *
     * BlockingClient::setOption simply delegates to the underlying async Client instance. See the
     * Artax\Client source code for a list of available Client::OP_* option keys.
     *
     * @param int $option A Client option constant
     * @param mixed $value The option value to assign
     * @throws \DomainException On unknown option key
     * @return self
     */
    public function setOption($option, $value) {
        $this->client->setOption($option, $value);

        return $this;
    }

    /**
     * Set multiple Client options at once
     *
     * BlockingClient::setAllOptions simply delegates to the underlying async Client instance. See
     * the Artax\Client source code for a list of available Client::OP_* option keys.
     *
     * @param array $options An array of the form [OP_CONSTANT => $value]
     * @throws \DomainException on Unknown option key
     * @return self
     */
    public function setAllOptions($option, $value) {
        $this->client->setAllOptions($option, $value);

        return $this;
    }
}
