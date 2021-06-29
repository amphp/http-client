<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InMemoryStream;
use Amp\ByteStream\InputStream;
use Amp\ByteStream\Payload;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Message;
use Amp\Http\Status;
use Amp\Promise;
use Amp\Success;

/**
 * An HTTP response.
 */
final class Response extends Message
{
    use ForbidSerialization;
    use ForbidCloning;

    /** @var string */
    private $protocolVersion;
    /** @var int */
    private $status;
    /** @var string */
    private $reason;
    /** @var Request */
    private $request;
    /** @var Payload */
    private $body;
    /** @var Promise<Trailers> */
    private $trailers;
    /** @var Response|null */
    private $previousResponse;

    public function __construct(
        string $protocolVersion,
        int $status,
        ?string $reason,
        array $headers,
        InputStream $body,
        Request $request,
        ?Promise $trailerPromise = null,
        ?Response $previousResponse = null
    ) {
        $this->setProtocolVersion($protocolVersion);
        $this->setStatus($status, $reason);
        $this->setHeaders($headers);
        $this->setBody($body);
        $this->request = $request;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->trailers = $trailerPromise ?? new Success(new Trailers([]));
        $this->previousResponse = $previousResponse;
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
        if (!\in_array($protocolVersion, ["1.0", "1.1", "2"], true)) {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \Error(
                "Invalid HTTP protocol version: " . $protocolVersion
            );
        }

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

    public function setStatus(int $status, ?string $reason = null): void
    {
        $this->status = $status;
        $this->reason = $reason ?? Status::getReason($status);
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
     * Retrieve the original Response instance associated with this Response instance.
     *
     * A given Response may be the result of one or more redirects. This method is a shortcut to
     * access information from the original Response that led to this response.
     *
     * @return Response
     */
    public function getOriginalResponse(): Response
    {
        if (empty($this->previousResponse)) {
            return $this;
        }

        return $this->previousResponse->getOriginalResponse();
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
     * @param string          $name Header name.
     * @param string|string[] $value Header value.
     */
    public function setHeader(string $name, $value): void
    {
        if (($name[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::setHeader($name, $value);
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param string          $name Header name.
     * @param string|string[] $value Header value.
     */
    public function addHeader(string $name, $value): void
    {
        if (($name[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::addHeader($name, $value);
    }

    public function setHeaders(array $headers): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::setHeaders($headers);
    }

    /**
     * Remove the specified header field from the message.
     *
     * @param string $name Header name.
     */
    public function removeHeader(string $name): void
    {
        parent::removeHeader($name);
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

    /**
     * @param Payload|InputStream|string|int|float|bool $body
     */
    public function setBody($body): void
    {
        if ($body instanceof Payload) {
            $this->body = $body;
        } elseif ($body === null) {
            $this->body = new Payload(new InMemoryStream);
        } elseif (\is_string($body)) {
            $this->body = new Payload(new InMemoryStream($body));
        } elseif (\is_scalar($body)) {
            $this->body = new Payload(new InMemoryStream(\var_export($body, true)));
        } elseif ($body instanceof InputStream) {
            $this->body = new Payload($body);
        } else {
            /** @noinspection PhpUndefinedClassInspection */
            throw new \TypeError("Invalid body type: " . \gettype($body));
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
