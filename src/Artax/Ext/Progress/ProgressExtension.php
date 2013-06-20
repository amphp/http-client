<?php

namespace Artax\Ext\Progress;

use Artax\Extension,
    Artax\Subject,
    Artax\Observable,
    Artax\ObservableClient,
    Artax\Response;

class ProgressExtension implements Extension, Observable {
    
    use Subject;
    
    const PROGRESS = 'progress';
    
    private $requests;
    private $progressBarSize = 40;
    private $progressBarIncrementChar = '=';
    private $progressBarEmptyIncrementChar = '.';
    private $progressBarLeadingChar = '>';
    private $eventSubscription;
    
    function __construct() {
        $this->requests = new \SplObjectStorage;
    }
    
    function setProgressBarSize($charWidth) {
        $this->progressBarSize = filter_var($charWidth, FILTER_VALIDATE_INT, ['options' => [
            'default' => 40,
            'min_range' => 10
        ]]);
        
        return $this;
    }
    
    function setProgressBarIncrementChar($char) {
        if (is_string($char) && strlen($char) === 1) {
            $this->progressBarIncrementChar = $char;
        } else {
            throw new \InvalidArgumentException(
                'Single character string required'
            );
        }
        
        return $this;
    }
    
    function setProgressBarEmptyIncrementChar($char) {
        if (is_string($char) && strlen($char) === 1) {
            $this->progressBarEmptyIncrementChar = $char;
        } else {
            throw new \InvalidArgumentException(
                'Single character string required'
            );
        }
        
        return $this;
    }
    
    function setProgressBarLeadingChar($char) {
        if (is_string($char) && strlen($char) === 1) {
            $this->progressBarLeadingChar = $char;
        } else {
            throw new \InvalidArgumentException(
                'Single character string required'
            );
        }
        
        return $this;
    }
    
    function unextend() {
        if ($this->eventSubscription) {
            $this->eventSubscription->cancel();
            $this->eventSubscription = NULL;
        }
        
        return $this;
    }
    
    function extend(ObservableClient $client) {
        $this->unextend();
        $this->eventSubscription = $client->subscribe([
            ObservableClient::REQUEST => function($dataArr) { $this->onRequest($dataArr); },
            ObservableClient::SOCKET => function($dataArr) { $this->onSocket($dataArr); },
            ObservableClient::DATA => function($dataArr) { $this->onData($dataArr); },
            ObservableClient::HEADERS => function($dataArr) { $this->onHeaders($dataArr); },
            ObservableClient::REDIRECT => function($dataArr) { $this->onRedirect($dataArr); },
            ObservableClient::RESPONSE => function($dataArr) { $this->clear($dataArr); },
            ObservableClient::CANCEL => function($dataArr) { $this->clear($dataArr); },
            ObservableClient::ERROR => function($dataArr) { $this->clear($dataArr); }
        ]);
        
        return $this;
    }
    
    private function onRequest(array $dataArr) {
        $request = $dataArr[0];
        $progress = new ProgressState;
        $this->requests->attach($request, $progress);
    }
    
    private function onSocket(array $dataArr) {
        $request = $dataArr[0];
        $progress = $this->requests->offsetGet($request);
        $progress->socketReadyAt = microtime(TRUE);
        $this->requests->attach($request, $progress);
    }
    
    private function onData(array $dataArr) {
        list($request, $dataRcvd) = $dataArr;
        
        $progress = $this->requests->offsetGet($request);
        
        $progress->bytesRcvd += strlen($dataRcvd);
        $elapsedTime = microtime(TRUE) - $progress->socketReadyAt;
        $progress->bytesPerSecond = round($progress->bytesRcvd / $elapsedTime);
        
        if (isset($progress->headerBytes, $progress->contentLength)) {
            $part = $progress->bytesRcvd;
            $whole = $progress->headerBytes + $progress->contentLength;
            $percentComplete = $part/$whole;
            $progress->percentComplete = $percentComplete;
            $progress->progressBar = $this->generateProgressBar($percentComplete);
        }
        
        $this->notify(self::PROGRESS, [$request, clone $progress]);
    }
    
    private function generateProgressBar($percentComplete) {
        $maxIncrements = $this->progressBarSize - 2;
        $displayIncrements = round($percentComplete * $maxIncrements);
        $emptyIncrements = $maxIncrements - $displayIncrements;
        
        $bar = '[';
        $bar.= str_repeat($this->progressBarIncrementChar, $displayIncrements);
        $bar.= $this->progressBarLeadingChar;
        $bar.= str_repeat($this->progressBarEmptyIncrementChar, $emptyIncrements);
        $bar.= ']';
        
        return $bar;
    }
    
    private function onHeaders(array $dataArr) {
        list($request, $parsedResponseArr) = $dataArr;
        
        $progress = $this->requests->offsetGet($request);
        
        $response = (new Response)->setAllHeaders($parsedResponseArr['headers']);
        
        if ($response->hasHeader('Content-Length')) {
             $progress->contentLength = (int) current($response->getHeader('Content-Length'));
             $progress->headerBytes = strlen($parsedResponseArr['trace']);
        }
    }
    
    private function onRedirect(array $dataArr) {
        $request = $dataArr[0];
        $progress = $this->requests->offsetGet($request);
        $redirectCount = $progress->redirectCount + 1;
        
        $progress = new ProgressState;
        $progress->redirectCount = $redirectCount;
        $this->requests->attach($request, $progress);
    }
    
    private function clear(array $dataArr) {
        $request = $dataArr[0];
        $this->requests->detach($request);
    }
    
}

