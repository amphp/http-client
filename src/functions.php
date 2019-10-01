<?php

use Amp\Http\Client\Request;

function isRetryAllowed(Request $request): bool
{
    // https://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
    return \in_array($request->getMethod(), ['GET', 'HEAD', 'PUT', 'DELETE'], true);
}
