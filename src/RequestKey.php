<?php

namespace Amp\Http\Client;

interface RequestKey
{
    public function getKey(Request $request): string;
}
