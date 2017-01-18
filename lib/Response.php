<?php

namespace Amp\Artax;

class Response extends Message {
    private $status = 200;
    private $reason = '';
    private $request;
    private $previousResponse;

    /**
     * Retrieve the response's three-digit HTTP status code
     *
     * @return int
     */
    public function getStatus(): int {
        return $this->status;
    }

    /**
     * Assign the response's three-digit HTTP status code
     *
     * @param int $status
     * @return self
     */
    public function setStatus(int $status): self {
        $this->status = $status;

        return $this;
    }

    /**
     * Retrieve the response's (possibly empty) reason phrase
     *
     * @return string
     */
    public function getReason(): string {
        return $this->reason;
    }

    /**
     * Assign the response's reason phrase
     *
     * @param string $reason
     * @return self
     */
    public function setReason(string $reason): self {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Retrieve the Request instance that resulted in this Response
     *
     * @return \Amp\Artax\Request|null
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * Associate a Request instance with this Response
     *
     * @param \Amp\Artax\Request $request
     * @return self
     */
    public function setRequest(Request $request): self {
        $this->request = $request;
        return $this;
    }

    /**
     * Retrieve the original Request associated with this Response
     *
     * A given Response may be the result of one or more redirects. This method is a shortcut to
     * access information from the original Request that led to this response.
     *
     * @return \Amp\Artax\Request|null
     */
    public function getOriginalRequest() {
        if (empty($this->previousResponse)) {
            return $this->request;
        }

        $current = $this;
        while ($current = $current->getPreviousResponse()) {
            $originalRequest = $current->getRequest();
        }

        return $originalRequest;
    }

    /**
     * If this Response is the result of a redirect traverse up the redirect history
     *
     * @return null|\Amp\Artax\Response|null
     */
    public function getPreviousResponse() {
        return $this->previousResponse;
    }

    /**
     * Associate this Response with a previous redirect Response
     *
     * @param \Amp\Artax\Response $response
     * @return self
     */
    public function setPreviousResponse(Response $response): self {
        $this->previousResponse = $response;
        return $this;
    }
}
