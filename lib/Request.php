<?php

namespace Amp\Artax;

class Request extends Message {
    private $method = '';
    private $uri = '';

    /**
     * Retrieve the request's HTTP method verb
     *
     * @return string
     */
    public function getMethod(): string {
        return $this->method;
    }

    /**
     * Specify the request's HTTP method verb
     *
     * @param string $method
     * @return self
     */
    public function setMethod(string $method): self {
        $this->method = $method;

        return $this;
    }

    /**
     * Retrieve the request's URI
     *
     * @return string
     */
    public function getUri(): string {
        return $this->uri;
    }

    /**
     * Specify the request's HTTP URI
     *
     * @param string
     * @return self
     */
    public function setUri(string $uri): self {
        $this->uri = $uri;

        return $this;
    }
}
