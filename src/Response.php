<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
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
    private $trailers;
    private $previousResponse;

    public function __construct(
        string $protocolVersion,
        int $status,
        string $reason,
        array $headers,
        InputStream $body,
        Request $request,
        ?Promise $trailerPromise = null,
        ?Response $previousResponse = null
    ) {
        $this->protocolVersion = $protocolVersion;
        $this->status = $status;
        $this->reason = $reason;
        $this->setBody($body);
        $this->request = $request;
        $this->previousResponse = $previousResponse;

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->trailers = $trailerPromise ?? new Success(new Trailers([]));
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

    public function setProtocolVersion(string $protocolVersion): void
    {
        $this->protocolVersion = $protocolVersion;
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

    public function setStatus(string $status): void
    {
        $this->status = $status;
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

    public function setReason(string $reason): void
    {
        $this->reason = $reason;
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

    public function setRequest(Request $request): void
    {
        $this->request = $request;
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

    public function setPreviousResponse(?Response $previousResponse): void
    {
        $this->previousResponse = $previousResponse;
    }

    /**
     * Assign a value for the specified header field by replacing any existing values for that field.
     *
     * @param string          $field Header name.
     * @param string|string[] $value Header value.
     */
    public function setHeader(string $field, $value): void
    {
        if (($field[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::setHeader($field, $value);
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param string          $field Header name.
     * @param string|string[] $value Header value.
     */
    public function addHeader(string $field, $value): void
    {
        if (($field[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::addHeader($field, $value);
    }

    public function setHeaders(array $headers): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::setHeaders($headers);
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $field Header name.
     */
    public function removeHeader(string $field): void
    {
        parent::removeHeader($field);
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

    public function setBody(InputStream $body): void
    {
        if ($body instanceof Payload) {
            $this->body = $body;
        } else {
            $this->body = new Payload($body);
        }
    }

    /**
     * @return Promise<Trailers>
     */
    public function getTrailers(): Promise
    {
        return $this->trailers;
    }

    /**
     * @param Promise<Trailers> $promise
     */
    public function setTrailers(Promise $promise): void
    {
        $this->trailers = $promise;
    }
}
