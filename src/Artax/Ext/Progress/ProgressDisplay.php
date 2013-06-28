<?php

namespace Artax\Ext\Progress;

class ProgressDisplay {
    
    function display(ProgressState $ps) {
        $kbRcvd = number_format(round($ps->bytesRcvd / 1024));
        $kbps = number_format(round($ps->bytesPerSecond / 1024));
        
        $output = "\r"; // Clears the previous iteration of the progress bar from the console
        $output.= $ps->progressBar;
        $output.= ' ' . $kbRcvd . ' KB';
        if (isset($ps->percentComplete)) {
            $output.= ' (' . round($ps->percentComplete * 100) . '%)';
        }
        $output.= ' @ ' . $kbps . ' KB/s';
        
        return $output;
    }
}

