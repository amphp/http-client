<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HarAttributes;
use Amp\Http\Client\Internal\ForbidCloning;
use Amp\Http\Client\Internal\ForbidSerialization;
use Amp\Http\Client\Request;
use Amp\Promise;
use function Amp\getCurrentTime;

final class RecordStartTime implements ApplicationInterceptor
{
    use ForbidCloning;
    use ForbidSerialization;

    public function request(Request $request, CancellationToken $cancellation, DelegateHttpClient $next): Promise
    {
        $request->setAttribute(HarAttributes::STARTED_DATE_TIME, new \DateTimeImmutable);
        $request->setAttribute(HarAttributes::TIME_START, getCurrentTime());

        return $next->request($request, $cancellation);
    }
}
