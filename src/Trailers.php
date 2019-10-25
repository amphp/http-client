<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\InvalidHeaderException;
use Amp\Http\Message;

final class Trailers extends Message
{
    use ForbidSerialization;

    /** @see https://tools.ietf.org/html/rfc7230#section-4.1.2 */
    public const DISALLOWED_TRAILERS = [
        "authorization" => true,
        "content-encoding" => true,
        "content-length" => true,
        "content-range" => true,
        "content-type" => true,
        "cookie" => true,
        "expect" => true,
        "host" => true,
        "pragma" => true,
        "proxy-authenticate" => true,
        "proxy-authorization" => true,
        "range" => true,
        "te" => true,
        "trailer" => true,
        "transfer-encoding" => true,
        "www-authenticate" => true,
    ];

    /**
     * @param string[]|string[][] $headers
     *
     * @throws InvalidHeaderException Thrown if a disallowed field is in the header values.
     */
    public function __construct(array $headers)
    {
        if (!empty($headers)) {
            $this->setHeaders($headers);
        }

        if (\array_intersect_key($this->getHeaders(), self::DISALLOWED_TRAILERS)) {
            throw new InvalidHeaderException('Disallowed field in trailers');
        }
    }
}
