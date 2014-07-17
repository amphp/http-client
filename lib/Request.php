<?php

namespace Artax;

class Request extends Message {
    private $method;
    private $uri;

    public function getMethod() {
        return $this->method;
    }

    public function setMethod($method) {
        $this->method = (string) $method;

        return $this;
    }

    public function getUri() {
        return $this->uri;
    }

    public function setUri($uri) {
        $this->uri = (string) $uri;

        return $this;
    }
}
