<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;
use League\Uri\Http;

final class ResolveBaseUri extends ModifyRequest
{
    public function __construct(string $baseUri)
    {
        /** @psalm-suppress DeprecatedMethod */
        parent::__construct(
            fn (Request $request) => $request->setUri(Http::createFromBaseUri($request->getUri(), $baseUri))
        );
    }
}
