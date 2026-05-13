<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\InvalidRequestException;
use Amp\Http\Client\Request;
use League\Uri\Http;

final class ResolveBaseUri extends ModifyRequest
{
    public function __construct(string $baseUri)
    {
        parent::__construct(
            fn (Request $request) => $request->setUri(
                Http::parse($request->getUri(), $baseUri)
                    ?? throw new InvalidRequestException($request, 'Invalid base URI provided: ' . $baseUri),
            ),
        );
    }
}
