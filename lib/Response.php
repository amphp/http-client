<?php

namespace Amp\Http\Client;

use Amp\ByteStream\InputStream;
use Amp\ByteStream\Payload;

/**
 * An HTTP response.
 *
 * This interface allows mocking responses and allows custom implementations.
 *
 * `DefaultClient` uses an anonymous class to implement this interface.
 */
final class Response
{
    private $protocolVersion;
    private $status;
    private $reason;
    private $request;
    private $previousResponse;
    private $headers;
    private $body;
    private $metaInfo;

    public function __construct(
        string $protocolVersion,
        int $status,
        string $reason,
        array $headers,
        InputStream $body,
        Request $request,
        ?Response $previousResponse,
        MetaInfo $metaInfo
    ) {
        $this->protocolVersion = $protocolVersion;
        $this->status = $status;
        $this->reason = $reason;
        $this->headers = $headers;
        $this->body = new Payload($body);
        $this->request = $request;
        $this->previousResponse = $previousResponse;
        $this->metaInfo = $metaInfo;
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

    /**
     * Retrieve the response's three-digit HTTP status code.
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
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

    /**
     * Does the message contain the specified header field (case-insensitive)?
     *
     * @param string $field Header name.
     *
     * @return bool
     */
    public function hasHeader(string $field): bool
    {
        return isset($this->headers[\strtolower($field)]);
    }

    /**
     * Retrieve the first occurrence of the specified header in the message.
     *
     * If multiple headers exist for the specified field only the value of the first header is returned. Applications
     * may use `getHeaderArray()` to retrieve a list of all header values received for a given field.
     *
     * A `null` return indicates the requested header field was not present.
     *
     * @param string $field Header name.
     *
     * @return string|null Header value or `null` if no header with name `$field` exists.
     */
    public function getHeader(string $field): ?string
    {
        return $this->headers[\strtolower($field)][0] ?? null;
    }

    /**
     * Retrieve all occurrences of the specified header in the message.
     *
     * Applications may use `getHeader()` to access only the first occurrence.
     *
     * @param string $field Header name.
     *
     * @return array Header values.
     */
    public function getHeaderArray(string $field): array
    {
        return $this->headers[\strtolower($field)] ?? [];
    }

    /**
     * Retrieve an associative array of headers matching field names to an array of field values.
     *
     * **Format**
     *
     * ```php
     * [
     *     "header-1" => [
     *         "value-1",
     *         "value-2",
     *     ],
     *     "header-2" => [
     *         "value-1",
     *     ],
     * ]
     * ```
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
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
     * @return MetaInfo
     */
    public function getMetaInfo(): MetaInfo
    {
        return $this->metaInfo;
    }
}
