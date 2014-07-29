<?php

namespace Artax;

class Response extends Message {
    private $status;
    private $reason;

    /**
     * Retrieve the response's three-digit HTTP status code
     *
     * @return int
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     * Assign the response's three-digit HTTP status code
     *
     * @param int $status
     * @return self
     */
    public function setStatus($status) {
        $this->status = (int) $status;

        return $this;
    }

    /**
     * Retrieve the response's (possibly empty) reason phrase
     *
     * @return string
     */
    public function getReason() {
        return $this->reason;
    }

    /**
     * Assign the response's reason phrase
     *
     * @param string $reason
     * @return self
     */
    public function setReason($reason) {
        $this->reason = (string) $reason;

        return $this;
    }
}
