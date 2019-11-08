<?php

namespace Amp\Http\Client;

final class HostRequestKey implements RequestKey
{
    public function getKey(Request $request): string
    {
        return $request->getUri()->getHost();
    }
}
