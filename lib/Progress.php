<?php

namespace Amp\Artax;

/**
 * Progress notifier
 *
 * The Amp\Artax\Client::request method returns a promise to asynchronously fulfill a response at some
 * point in the future when it completes. We can react to progress events on the promise using the
 * Promise::watch() method. The Amp\Artax\Progress class hides the HTTP protocol details needed to
 * create an accurate progress bar from the progress events emitted by the promise.
 *
 * Example:
 *
 *      <?php
 *      $client = new Amp\Artax\Client;
 *      $promise = $client->request('http://www.google.com');
 *      $promise->watch(new Progress(function($data) {
 *          // what to do with progress info when broadcast by the promise
 *          var_dump($data['fraction_complete'] * 100);
 *      });
 *      $response = \Amp\wait($promise);
 */
class Progress {
    const CONNECTING = 0;
    const CONNECTED = 1;
    const SENDING_REQUEST = 2;
    const AWAITING_RESPONSE = 4;
    const REDIRECTING = 8;
    const READING_LENGTH = 16;
    const READING_UNKNOWN = 32;
    const COMPLETE = 64;
    const ERROR = 65;

    private $state = self::CONNECTING;
    private $msUpdateFrequency;
    private $lastBroadcastAt;
    private $connectedAt;
    private $bytesRcvd;
    private $bytesPerSecond;
    private $headerLength;
    private $contentLength;
    private $fractionComplete;
    private $redirectCount;

    /**
     * @param callable $onUpdateCallback
     * @param int $msUpdateFrequency Limit updates to fire only once every n milliseconds
     */
    public function __construct(callable $onUpdateCallback, $msUpdateFrequency = 30) {
        $this->onUpdateCallback = $onUpdateCallback;
        $this->msUpdateFrequency = ($msUpdateFrequency / 1000);
    }

    /**
     * A convenience method allowing applications to pass instances directly to Promise::watch()
     *
     * @param $progress
     * @return void
     */
    public function __invoke($progress) {
        $this->onPromiseUpdate($progress);
    }

    /**
     * Watch for request progress events
     *
     * @param array $progress
     * @return void
     */
    public function onPromiseUpdate(array $progress) {
        $event = array_shift($progress);
        switch ($event) {
            case Notify::SOCK_PROCURED:
                $this->connectedAt = microtime(true);
                $this->state = self::CONNECTED;
                break;
            case Notify::SOCK_DATA_OUT:
                $this->state = self::SENDING_REQUEST;
                break;
            case Notify::REQUEST_SENT:
                $this->state = self::AWAITING_RESPONSE;
                break;
            case Notify::SOCK_DATA_IN:
                $this->onSockDataIn($progress);
                break;
            case Notify::RESPONSE_HEADERS:
                $this->onResponseHeaders($progress);
                break;
            case Notify::REDIRECT:
                $this->redirectCount++;
                $this->connectedAt = null;
                $this->bytesRcvd = null;
                $this->bytesPerSecond = null;
                $this->headerLength = null;
                $this->contentLength = null;
                $this->fractionComplete = null;
                $this->state = self::REDIRECTING;
                break;
            case Notify::RESPONSE:
                $this->fractionComplete = 1.00;
                $this->state = self::COMPLETE;
                break;
            case Notify::ERROR:
                $this->state = self::ERROR;
                break;
        }

        $now = microtime(true);
        if ($this->state & self::COMPLETE || ($now - $this->lastBroadcastAt) > $this->msUpdateFrequency) {
            $this->lastBroadcastAt = $now;
            $onUpdateCallback = $this->onUpdateCallback;
            $onUpdateCallback($this->getStats());
        }
    }

    private function onSockDataIn($progress) {
        list($rawDataRcvd) = $progress;
        $this->bytesRcvd += strlen($rawDataRcvd);
        $elapsedTime = microtime(true) - $this->connectedAt;
        $this->bytesPerSecond = round($this->bytesRcvd / $elapsedTime);

        if (isset($this->headerLength, $this->contentLength)) {
            $whole = $this->headerLength + $this->contentLength;
            $this->fractionComplete = round(($this->bytesRcvd/$whole), 3);
        }
    }

    private function onResponseHeaders($progress) {
        list($parsedResponseArr) = $progress;
        $this->headerLength = strlen($parsedResponseArr['trace']);
        $response = (new Response)->setAllHeaders($parsedResponseArr['headers']);
        if ($response->hasHeader('Content-Length')) {
            $this->contentLength = (int) $response->getHeader('Content-Length')[0];
            $this->state = self::READING_LENGTH;
        } else {
            $this->state = self::READING_UNKNOWN;
        }
    }

    private function getStats() {
        return [
            'request_state' => $this->state,
            'connected_at' => $this->connectedAt,
            'bytes_rcvd' => $this->bytesRcvd,
            'bytes_per_second' => $this->bytesPerSecond,
            'header_length' => $this->headerLength,
            'content_length' => $this->contentLength,
            'fraction_complete' => $this->fractionComplete,
            'redirect_count' => $this->redirectCount,
        ];
    }
}
