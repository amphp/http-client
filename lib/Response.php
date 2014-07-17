<?php

namespace Artax;

class Response extends Message {
    private $status;
    private $reason;

    public function getStatus() {
        return $this->status;
    }

    public function setStatus($status) {
        $this->status = (int) $status;

        return $this;
    }

    public function getReason() {
        return $this->reason;
    }

    public function setReason($reason) {
        $this->reason = $reason;

        return $this;
    }
}
