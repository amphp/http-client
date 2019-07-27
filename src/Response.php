<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\Payload;
use Amp\Http\Message;
use Amp\Promise;
use Amp\Success;

/**
 * An HTTP response.
 *
 * This interface allows mocking responses and allows custom implementations.
 *
 * `DefaultClient` uses an anonymous class to implement this interface.
 */
final class Response extends Message
{
    private $protocolVersion;
    private $status;
    private $reason;
    private $request;
    private $body;
    private $completionPromise;
    private $previousResponse;

    public function __construct(
        string $protocolVersion,
        int $status,
        string $reason,
        array $headers,
        InputStream $body,
        Request $request,
        ?Promise $completionPromise = null,
        ?Response $previousResponse = null
    ) {
        $this->protocolVersion = $protocolVersion;
        $this->status = $status;
        $this->reason = $reason;
        $this->body = new Payload($body);
        $this->request = $request;
        $this->completionPromise = $completionPromise ?? new Success;
        $this->previousResponse = $previousResponse;

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->setHeaders($headers);
    }

    /**
     * Retrieve the requests's HTTP protocol version.
     *
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $protocolVersion): self
    {
        $clone = clone $this;
        $clone->protocolVersion = $protocolVersion;

        return $clone;
    }

    /**
     * Retrieve the response's three-digit HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    public function withStatus(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    /**
     * Retrieve the response's (possibly empty) reason phrase.
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    public function withReason(string $reason): self
    {
        $clone = clone $this;
        $clone->reason = $reason;

        return $clone;
    }

    /**
     * Retrieve the Request instance that resulted in this Response instance.
     *
     * @return Request
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    public function withRequest(Request $request): self
    {
        $clone = clone $this;
        $clone->request = $request;

        return $clone;
    }

    /**
     * Retrieve the original Request instance associated with this Response instance.
     *
     * A given Response may be the result of one or more redirects. This method is a shortcut to
     * access information from the original Request that led to this response.
     *
     * @return Request
     */
    public function getOriginalRequest(): Request
    {
        if (empty($this->previousResponse)) {
            return $this->request;
        }

        return $this->previousResponse->getOriginalRequest();
    }

    /**
     * If this Response is the result of a redirect traverse up the redirect history.
     *
     * @return Response|null
     */
    public function getPreviousResponse(): ?Response
    {
        return $this->previousResponse;
    }

    public function withPreviousResponse(?Response $previousResponse): self
    {
        $clone = clone $this;
        $clone->previousResponse = $previousResponse;

        return $clone;
    }

    /**
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Response
     */
    public function withHeader(string $field, string $value): self
    {
        $clone = clone $this;
        $clone->setHeader($field, $value);

        return $clone;
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param string $field Header name.
     * @param string $value Header value.
     *
     * @return Response
     */
    public function withAddedHeader(string $field, string $value): self
    {
        $clone = clone $this;
        $clone->addHeader($field, $value);

        return $clone;
    }

    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        /** @noinspection PhpUnhandledExceptionInspection */
        $clone->setHeaders($headers);

        return $clone;
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $field Header name.
     *
     * @return Response
     */
    public function withoutHeader(string $field): self
    {
        $clone = clone $this;
        $clone->removeHeader($field);

        return $clone;
    }

    /**
     * Retrieve the response body.
     *
     * Note: If you stream a Message, you can't consume the payload twice.
     *
     * @return Payload
     */
    public function getBody(): Payload
    {
        return $this->body;
    }

    public function withBody(InputStream $body): self
    {
        $clone = clone $this;
        $clone->body = new Payload($body);

        return $clone;
    }

    public function getCompletionPromise(): Promise
    {
        return $this->completionPromise;
    }

    public function withCompletionPromise(Promise $promise): self
    {
        $clone = clone $this;
        $clone->completionPromise = $promise;

        return $clone;
    }
}
