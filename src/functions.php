<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\EventInvoker;

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
    if ($request->isStarted()) {
        return $requestHandler($request);
    }

    foreach ($eventListeners as $eventListener) {
        $request->addEventListener($eventListener);
    }

    events()->requestStart($request);

    try {
        $response = $requestHandler($request);
    } catch (HttpException $exception) {
        events()->requestFailed($request, $exception);

        throw $exception;
    }

    $response->getTrailers()->map(fn () => events()->requestEnd($request, $response))->ignore();
    $response->getTrailers()->catch(fn (\Throwable $e) => events()->requestFailed(
        $request,
        $e instanceof HttpException ? $e : new HttpException('Unexpected exception: ' . $e->getMessage(), previous: $e)
    ))->ignore();

    return $response;
}
