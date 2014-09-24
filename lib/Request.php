<?php

namespace Amp\Artax;

class Request extends Message {
    private $method;
    private $uri;

    /**
     * Retrieve the request's HTTP method verb
     *
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Specify the request's HTTP method verb
     *
     * @param string $method
     * @return self
     */
    public function setMethod($method) {
        $this->method = (string) $method;

        return $this;
    }

    /**
     * Retrieve the request's URI
     *
     * @return string
     */
    public function getUri() {
        return $this->uri;
    }

    /**
     * Specify the request's HTTP URI
     *
     * @param string
     * @return self
     */
    public function setUri($uri) {
        $this->uri = (string) $uri;

        return $this;
    }
}
