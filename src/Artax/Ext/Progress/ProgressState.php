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
        $output.= $this->progressBar;
        $output.= ' ' . $kbRcvd . ' KB';
        if (isset($this->percentComplete)) {
            $output.= ' (' . round($this->percentComplete * 100) . '%)';
        }
        $output.= ' @ ' . $kbps . ' KB/s';
        
        return $output;
    }
}

