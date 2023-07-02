<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ByteStream\Payload;
use Amp\ByteStream\ReadableStream;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Future;
use Amp\Http\HttpMessage;
use Amp\Http\HttpResponse;

/**
 * An HTTP response.
 *
 * @psalm-import-type HeaderParamValueType from HttpMessage
 * @psalm-import-type HeaderParamArrayType from HttpMessage
 * @psalm-type ProtocolVersion = '1.0'|'1.1'|'2'
 */
final class Response extends HttpResponse
{
    use ForbidSerialization;
    use ForbidCloning;

    /** @var ProtocolVersion */
    private string $protocolVersion;

    private Request $request;

    private Payload $body;

    /** @var Future<Trailers> */
    private Future $trailers;

    private ?Response $previousResponse;

    /**
     * @param ProtocolVersion $protocolVersion
     * @param HeaderParamArrayType $headers
     * @param Future<Trailers>|null $trailers
     *
     * @throws \Amp\Http\InvalidHeaderException
     */
    public function __construct(
        string $protocolVersion,
        int $status,
        ?string $reason,
        array $headers,
        ReadableStream|string|null $body,
        Request $request,
        ?Future $trailers = null,
        ?Response $previousResponse = null
    ) {
        parent::__construct($status, $reason);

        $this->setProtocolVersion($protocolVersion);
        $this->setHeaders($headers);
        $this->setBody($body);
        $this->request = $request;
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->trailers = $trailers ?? Future::complete(new Trailers([]));
        $this->previousResponse = $previousResponse;
    }

    /**
     * Retrieve the HTTP protocol version used for the request.
     *
     * @return ProtocolVersion
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    /**
     * @param ProtocolVersion $protocolVersion
     */
    public function setProtocolVersion(string $protocolVersion): void
    {
        if (!\in_array($protocolVersion, ["1.0", "1.1", "2"], true)) {
            throw new \Error(
                "Invalid HTTP protocol version: " . $protocolVersion
            );
        }

        $this->protocolVersion = $protocolVersion;
    }

    public function setStatus(int $status, ?string $reason = null): void
    {
        parent::setStatus($status, $reason);
    }

    /**
     * Retrieve the Request instance that resulted in this Response instance.
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
     * @param non-empty-string $name Header name.
     * @param HeaderParamValueType $value Header value.
     */
    public function setHeader(string $name, array|string $value): void
    {
        if (($name[0] ?? ":") === ":") {
            throw new \Error("Header name cannot be empty or start with a colon (:)");
        }

        parent::setHeader($name, $value);
    }

    /**
     * Assign a value for the specified header field by adding an additional header line.
     *
     * @param non-empty-string $name Header name.
     * @param HeaderParamValueType $value Header value.
     */
    public function addHeader(string $name, array|string $value): void
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

    public function replaceHeaders(array $headers): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        parent::replaceHeaders($headers);
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
     */
    public function getBody(): Payload
    {
        return $this->body;
    }

    public function setBody(ReadableStream|string|null $body): void
    {
        $this->body = match (true) {
            $body instanceof Payload => $body,
            $body instanceof ReadableStream, \is_string($body) => new Payload($body),
            $body === null => new Payload(''),
        };
    }

    /**
     * @return Future<Trailers>
     */
    public function getTrailers(): Future
    {
        return $this->trailers;
    }

    /**
     * @param Future<Trailers> $future
     */
    public function setTrailers(Future $future): void
    {
        $this->trailers = $future;
    }
}
