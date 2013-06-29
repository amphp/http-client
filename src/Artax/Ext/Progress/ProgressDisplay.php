<?php

namespace Artax\Ext\Progress;

class ProgressDisplay {
    
    static function display(ProgressState $ps) {
        $kbRcvd = number_format(round($ps->bytesRcvd / 1024));
        $kbps = number_format(round($ps->bytesPerSecond / 1024));
        
        $output = $ps->progressBar . ' ' . $kbRcvd . ' KB';
        
        if (isset($ps->percentComplete)) {
            $output.= ' (' . round($ps->percentComplete * 100) . '%)';
        }
        $output.= ' @ ' . $kbps . ' KB/s';
        
        return $output;
    }
}

