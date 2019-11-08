<?php

namespace Amp\Http\Client;

final class HostKeyExtractor implements KeyExtractor
{
    public function getKey(Request $request): string
    {
        return $request->getUri()->getHost();
    }
}
