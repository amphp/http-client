<?php

namespace Artax\Ext\Progress;

class ProgressState {
    
    public $socketReadyAt;
    public $redirectCount = 0;
    public $bytesRcvd = 0;
    public $headerBytes;
    public $contentLength;
    public $percentComplete;
    public $bytesPerSecond;
    public $progressBar;
    
    function display() {
        $kbRcvd = number_format(round($this->bytesRcvd / 1024));
        $kbps = number_format(round($this->bytesPerSecond / 1024));
        
        $output = "\r"; // Clears the previous iteration of the progress bar from the console
        
        if (isset($this->progressBar)) {
            $totalKb = number_format(round(($this->headerBytes + $this->contentLength) / 1024));
            $output.= $this->progressBar;
            $output.= ' ' . $kbRcvd . '/' . $totalKb . ' KB';
            $output.= ' (' . round($this->percentComplete * 100) . '%)';
        } else {
            $output.= ' ' . $kbRcvd . '/[UNKNOWN] KB';
        }
        
        $output.= ' @ ' . $kbps . ' KB/s';
        
        return $output;
    }
}

