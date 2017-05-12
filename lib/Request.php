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
        if (!is_string($method)) {
            throw new \DomainException(sprintf(
                "Method must be of type string, %s given"
                gettype($method)
            ));
        }
        
        $this->method = $method;

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
        if (!is_string($uri)) {
            throw new \DomainException(sprintf(
                "URI must be of type string, %s given"
                gettype($uri)
            ));
        }
        
        $this->uri = $uri;

        return $this;
    }
}
