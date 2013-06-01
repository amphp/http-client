<?php

namespace Artax;

class Response extends Message {
    
    private $status;
    private $reason;
    
    function getStatus() {
        return $this->status;
    }
    
    function setStatus($status) {
        $this->status = (int) $status;
        
        return $this;
    }
    
    function getReason() {
        return $this->reason;
    }
    
    function setReason($reason) {
        $this->reason = $reason;
        
        return $this;
    }
    
}

