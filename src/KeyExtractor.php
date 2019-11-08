<?php

namespace Amp\Http\Client;

interface KeyExtractor
{
    public function getKey(Request $request): string;
}
