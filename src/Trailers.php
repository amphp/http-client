<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\ForbidSerialization;
use Amp\Http\HttpMessage;
use Amp\Http\InvalidHeaderException;

final class Trailers extends HttpMessage
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

        $disallowedHeaders = \array_intersect_key($this->getHeaders(), self::DISALLOWED_TRAILERS);
        if ($disallowedHeaders) {
            throw new InvalidHeaderException(
                'Disallowed field(s) in trailers: ' . \implode(', ', \array_keys($disallowedHeaders)),
            );
        }
    }
}
