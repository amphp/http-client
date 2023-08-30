<?php declare(strict_types=1);

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\EventInvoker;
use Amp\Http\Client\Internal\Phase;

function events(): EventListener
{
    return EventInvoker::get();
}

/**
 * @param array<EventListener> $eventListeners
 * @param \Closure(Request):Response $requestHandler
 */
function processRequest(Request $request, array $eventListeners, \Closure $requestHandler): Response
{
    if (EventInvoker::getPhase($request) !== Phase::Unprocessed) {
        return $requestHandler($request);
    }

    foreach ($eventListeners as $eventListener) {
        $request->addEventListener($eventListener);
    }

    events()->requestStart($request);

    try {
        $response = $requestHandler($request);
    } catch (\Throwable $exception) {
        events()->requestFailed($request, $exception);

        throw $exception;
    }

    $trailers = $response->getTrailers();
    $trailers->map(fn () => events()->requestEnd($request, $response))->ignore();
    $trailers->catch(fn (\Throwable $exception) => events()->requestFailed($request, $exception))->ignore();

    return $response;
}
