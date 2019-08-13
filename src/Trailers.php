<?php

namespace Amp\Http\Client;

use Amp\Http\InvalidHeaderException;
use Amp\Http\Message;

final class Trailers extends Message
{
    /** @see https://tools.ietf.org/html/rfc7230#section-4.1.2 */
    public const DISALLOWED_TRAILERS = [
        "authorization" => 1,
        "content-encoding" => 1,
        "content-length" => 1,
        "content-range" => 1,
        "content-type" => 1,
        "cookie" => 1,
        "expect" => 1,
        "host" => 1,
        "pragma" => 1,
        "proxy-authenticate" => 1,
        "proxy-authorization" => 1,
        "range" => 1,
        "te" => 1,
        "trailer" => 1,
        "transfer-encoding" => 1,
        "www-authenticate" => 1,
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
