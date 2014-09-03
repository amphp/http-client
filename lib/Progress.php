<?php

namespace Artax;

/**
 * Progress notifier
 *
 * The Artax\Client::request method returns a promise to asynchronously fulfill a response at some
 * point in the future when it completes. We can react to progress events on the promise using the
 * Promise::watch() method. The Artax\Progress class hides the HTTP protocol details needed to
 * create an accurate progress bar from the progress events emitted by the promise.
 *
 * Example:
 *
 *      <?php
 *      $client = new Artax\Client;
 *      $promise = $client->request('http://www.google.com');
 *      $promise->watch(new Progress(function($data) {
 *          // what to do with progress info when broadcast by the promise
 *          printf("\r%s %s%%\r", $data['bar'], round($data['percent_complete'] * 100));
 *      });
 *      $response = $promise->wait();
 *
 * @package Artax
 */
class Progress {
    const OP_BAR_WIDTH = 1;
    const OP_BAR_FULL_CHAR = 2;
    const OP_BAR_EMPTY_CHAR = 3;
    const OP_BAR_LEAD_CHAR = 4;
    const OP_MS_UPDATE_FREQUENCY = 5;

    private static $defaultOptions = [
        self::OP_BAR_WIDTH => 60,
        self::OP_BAR_FULL_CHAR => '=',
        self::OP_BAR_EMPTY_CHAR => '.',
        self::OP_BAR_LEAD_CHAR => '>',
        self::OP_MS_UPDATE_FREQUENCY => 20,
    ];

    private $options;
    private $lastBroadcastAt;
    private $socketProcuredAt;
    private $elapsedTime;
    private $bytesRcvd;
    private $bytesPerSecond;
    private $headerBytes;
    private $contentLength;
    private $percentComplete;
    private $redirectCount;
    private $isRequestSendComplete;
    private $isComplete;
    private $isError;

    /**
     * @param callable $onUpdateCallback
     * @TODO Allow custom option specification
     */
    public function __construct(callable $onUpdateCallback) {
        $this->onUpdateCallback = $onUpdateCallback;
        $this->options = self::$defaultOptions;
    }

    /**
     * A convenience allowing applications to pass instances directly to Promise::watch()
     *
     * @param $progress
     * @return void
     */
    public function __invoke($progress) {
        $this->onPromiseUpdate($progress);
    }

    /**
     * Invoke on response progress via Promise::watch()
     *
     * @param array $progress
     * @return void
     */
    public function onPromiseUpdate(array $progress) {
        $event = array_shift($progress);
        switch ($event) {
            case Notify::SOCK_PROCURED:
                $this->socketProcuredAt = microtime(true);
                break;
            case Notify::REQUEST_SENT:
                $this->isRequestSendComplete = true;
                break;
            case Notify::SOCK_DATA_IN:
                $this->onSockDataIn($progress);
                break;
            case Notify::RESPONSE_HEADERS:
                $this->onResponseHeaders($progress);
                break;
            case Notify::REDIRECT:
                $this->redirectCount++;
                $this->socketProcuredAt = null;
                $this->elapsedTime = null;
                $this->bytesRcvd = null;
                $this->bytesPerSecond = null;
                $this->headerBytes = null;
                $this->contentLength = null;
                $this->percentComplete = null;
                $this->isRequestSendComplete = null;
                break;
            case Notify::RESPONSE:
                $this->percentComplete = 1.0;
                $this->isComplete = true;
                break;
            case Notify::ERROR:
                $this->isComplete = $this->isError = true;
                break;
        }

        $now = microtime(true);
        $msUpdateFrequency = $this->options[self::OP_MS_UPDATE_FREQUENCY] / 1000;
        if ($this->isComplete || ($now - $this->lastBroadcastAt) > $msUpdateFrequency) {
            $this->lastBroadcastAt = $now;
            $onUpdateCallback = $this->onUpdateCallback;
            $onUpdateCallback($this->getStats());
        }
    }

    private function onSockDataIn($progress) {
        list($rawDataRcvd) = $progress;
        $this->bytesRcvd += strlen($rawDataRcvd);
        $this->elapsedTime = microtime(true) - $this->socketProcuredAt;
        $this->bytesPerSecond = round($this->bytesRcvd / $this->elapsedTime);

        if (isset($this->headerBytes, $this->contentLength)) {
            $whole = $this->headerBytes + $this->contentLength;
            $this->percentComplete = $this->bytesRcvd/$whole;
        }
    }

    private function onResponseHeaders($progress) {
        list($parsedResponseArr) = $progress;
        $this->headerBytes = strlen($parsedResponseArr['trace']);
        $response = (new Response)->setAllHeaders($parsedResponseArr['headers']);
        if ($response->hasHeader('Content-Length')) {
            $this->contentLength = (int) $response->getHeader('Content-Length')[0];
        }
    }

    private function getStats() {
        if ($this->headerBytes) {
            $bar = $this->generateBar();
        } elseif ($this->isRequestSendComplete) {
            $bar = $this->isComplete ? '[COMPLETE]            ' : '[DETERMINING_LENGTH]';
        } elseif ($this->socketProcuredAt) {
            $bar = '[SENDING REQUEST]   ';
        } else {
            $bar = '[CONNECTING]        ';
        }

        return [
            'sock_procured_at' => $this->socketProcuredAt,
            'redirect_count' => $this->redirectCount,
            'bytes_rcvd' => $this->bytesRcvd,
            'header_bytes' => $this->headerBytes,
            'content_length' => $this->contentLength,
            'percent_complete' => $this->percentComplete,
            'bytes_per_second' => $this->bytesPerSecond,
            'is_request_sent' => (bool) $this->isRequestSendComplete,
            'is_complete' => (bool) $this->isComplete,
            'is_error' =>(bool)  $this->isError,
            'bar' => $bar
        ];
    }

    private function generateBar() {
        if ($this->isError) {
            return '[ERROR]';
        } elseif ($this->isComplete || isset($this->headerBytes, $this->contentLength)) {
            return $this->generateBarOfKnownSize();
        } else {
            return $this->generateBarOfUnknownSize();
        }
    }

    private function generateBarOfKnownSize() {
        $maxIncrements = $this->options[self::OP_BAR_WIDTH] - 3;
        $fullIncrements = round($this->percentComplete * $maxIncrements);
        $emptyIncrements = $maxIncrements - $fullIncrements;

        $bar[] = '[';
        $bar[] = str_repeat($this->options[self::OP_BAR_FULL_CHAR], $fullIncrements);
        $bar[] = $this->options[self::OP_BAR_LEAD_CHAR];
        $bar[] = str_repeat($this->options[self::OP_BAR_EMPTY_CHAR], $emptyIncrements);
        $bar[] = ']';

        return implode($bar);
    }

    private function generateBarOfUnknownSize() {
        $msg = 'SIZE UNKNOWN';
        $maxIncrements = $this->options[self::OP_BAR_WIDTH] - 2;
        $emptyChar = $this->options[self::OP_BAR_EMPTY_CHAR];
        $emptyIncrements = $maxIncrements - strlen($msg);
        if ($emptyIncrements % 2 === 0) {
            $leftEmpty = $rightEmpty = $emptyIncrements / 2;
        } else {
            $leftEmpty = floor($emptyIncrements / 2);
            $rightEmpty = $leftEmpty + 1;
        }

        $bar[] = '[';
        $bar[] = str_repeat($emptyChar, $leftEmpty);
        $bar[] = $msg;
        $bar[] = str_repeat($emptyChar, $rightEmpty);
        $bar[] = ']';

        return implode($bar);
    }
}
