<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Cancellation;

/**
 * Base HTTP client interface for use in {@see ApplicationInterceptor}.
 *
 * Applications and implementations should depend on {@see HttpClient} instead. The intent of this interface is to
 * allow static analysis tools to find interceptors that forget to pass the cancellation down, because the cancellation
 * is optional.
 *
 * The implementation must ensure that events are called on {@see events()} and may use {@see request()} for that.
 *
 * @see HttpClient
 */
interface DelegateHttpClient
{
    /**
     * Request a specific resource from an HTTP server.
     *
     * @throws HttpException
     */
    public function request(Request $request, Cancellation $cancellation): Response;
}
